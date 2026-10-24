<?php

namespace Tests\Feature\Public;

use App\Enums\Role;
use App\Filament\Resources\Testimonials\Pages\ManageTestimonials;
use App\Filament\Resources\Testimonials\TestimonialResource;
use App\Models\Country;
use App\Models\Testimonial;
use App\Models\User;
use App\Notifications\StoryAlert;
use App\Notifications\StoryReceived;
use Database\Seeders\TaxonomySeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class StoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TaxonomySeeder::class);
        Storage::fake('public_media');
        Country::firstOrCreate(['name' => 'Pakistan'], ['code' => 'PK', 'slug' => 'pakistan']);
        config(['sas.staff_notification_email' => 'staff@example.test']);
    }

    private function payload(array $over = []): array
    {
        return $over + [
            'name' => 'Amina', 'email' => 'Amina@Example.test', 'gender' => 'female', 'country' => 'Pakistan', 'university' => 'Tsinghua University',
            'scholarship' => 'Chinese Government Scholarship', 'story' => str_repeat('I found the scholarship on the official page and applied early. ', 5), 'consent' => '1',
        ];
    }

    private function staff(Role $role = Role::Administrator): User
    {
        Filament::setCurrentPanel('admin');
        $user = User::factory()->role($role)->create();
        $user->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP', 'app_authentication_recovery_codes' => ['aaaa-bbbb']])->save();
        $this->actingAs($user);

        return $user;
    }

    public function test_every_public_page_has_the_vertical_share_your_story_tab_except_the_form_itself(): void
    {
        foreach (['/', '/scholarships', '/about', '/blog', '/stories'] as $path) {
            $this->get($path)->assertOk()->assertSee('Share Your Story')->assertSee(route('stories.create'), false)->assertSee('[writing-mode:vertical-rl]', false);
        }
        $this->get('/share-your-story')->assertOk()->assertDontSee('[writing-mode:vertical-rl]', false)->assertSee('Your story')->assertSee('Pakistan');
    }

    public function test_a_story_is_saved_as_pending_with_consent_and_nothing_is_public_yet(): void
    {
        Notification::fake();

        $this->post('/share-your-story', $this->payload(['photo' => UploadedFile::fake()->image('me.png', 900, 600)]))->assertRedirect(route('stories.thanks'));

        $t = Testimonial::firstOrFail();
        $this->assertSame('pending', $t->review_status);
        $this->assertSame('submitted', $t->source);
        $this->assertFalse($t->published);
        $this->assertTrue($t->consent_given);
        $this->assertNotNull($t->consent_date);
        $this->assertStringContainsString('may publish my story', $t->consent_text);
        $this->assertSame('amina@example.test', $t->submitter_email);
        $this->assertSame('female', $t->gender);
        $this->assertSame('Pakistan', $t->country);
        $this->assertNotEmpty($t->slug);

        // the photo was re-saved as a plain square JPEG under a random name
        $this->assertStringStartsWith('stories/', $t->photo_path);
        Storage::disk('public_media')->assertExists($t->photo_path);
        [$w, $h, $type] = getimagesizefromstring(Storage::disk('public_media')->get($t->photo_path));
        $this->assertSame([640, 640, IMAGETYPE_JPEG], [$w, $h, $type]);

        Notification::assertSentOnDemand(StoryReceived::class, fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'amina@example.test');
        Notification::assertSentOnDemand(StoryAlert::class, fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'staff@example.test');

        $this->get('/stories')->assertOk()->assertDontSee('Amina');
        $this->get('/stories/'.$t->slug)->assertNotFound();
        $this->get('/')->assertDontSee('Tsinghua University');
        $this->assertStringNotContainsString($t->slug, $this->get('/sitemap.xml')->getContent());
        $this->get('/share-your-story/thanks')->assertNotFound(); // the thank-you page is only for someone who just sent a story
    }

    public function test_the_form_checks_what_it_must(): void
    {
        $this->post('/share-your-story', $this->payload(['story' => 'Too short.']))->assertSessionHasErrors('story');
        $this->post('/share-your-story', $this->payload(['consent' => null]))->assertSessionHasErrors('consent');
        $this->post('/share-your-story', $this->payload(['country' => 'Atlantis']))->assertSessionHasErrors('country');
        $this->post('/share-your-story', $this->payload(['gender' => 'robot']))->assertSessionHasErrors('gender');
        $this->post('/share-your-story', $this->payload(['email' => 'nope']))->assertSessionHasErrors('email');
        $this->post('/share-your-story', $this->payload(['photo' => UploadedFile::fake()->create('virus.exe', 20, 'application/x-msdownload')]))->assertSessionHasErrors('photo');
        $this->post('/share-your-story', $this->payload(['photo' => UploadedFile::fake()->image('tiny.png', 50, 50)]))->assertSessionHasErrors('photo');
        $this->post('/share-your-story', $this->payload(['photo' => UploadedFile::fake()->image('huge.jpg', 600, 600)->size(5000)]))->assertSessionHasErrors('photo');
        $this->assertSame(0, Testimonial::count());
    }

    public function test_robots_and_double_clicks_do_not_create_extra_stories_and_abuse_is_throttled(): void
    {
        Notification::fake();
        $this->post('/share-your-story', $this->payload(['website' => 'http://spam.example']))->assertRedirect(route('stories.thanks'));
        $this->assertSame(0, Testimonial::count(), 'a filled hidden field means a robot');

        $this->post('/share-your-story', $this->payload());
        $this->post('/share-your-story', $this->payload());
        $this->assertSame(1, Testimonial::count(), 'the same story twice is saved once');

        $this->post('/share-your-story', $this->payload(['story' => str_repeat('A different story about my first semester. ', 8)]));
        $this->post('/share-your-story', $this->payload(['story' => str_repeat('A third one about the visa interview. ', 9)]));
        $this->post('/share-your-story', $this->payload(['story' => str_repeat('A fourth one about my dormitory. ', 10)]))->assertStatus(429);
    }

    public function test_the_sketch_picture_depends_on_gender_when_there_is_no_photo(): void
    {
        $this->assertStringEndsWith('/images/avatars/male.svg', (new Testimonial(['gender' => 'male']))->avatarUrl());
        $this->assertStringEndsWith('/images/avatars/female.svg', (new Testimonial(['gender' => 'female']))->avatarUrl());
        $this->assertStringEndsWith('/images/avatars/neutral.svg', (new Testimonial(['gender' => 'other']))->avatarUrl());
        $this->assertStringEndsWith('/images/avatars/neutral.svg', (new Testimonial)->avatarUrl());
        $withPhoto = new Testimonial(['gender' => 'male', 'photo_path' => 'stories/a.jpg']);
        $this->assertStringContainsString('stories/a.jpg', $withPhoto->avatarUrl());
        foreach (['male', 'female', 'neutral'] as $name) {
            $this->assertFileExists(public_path("images/avatars/$name.svg"));
        }
    }

    public function test_an_administrator_edits_approves_and_the_story_goes_live_everywhere(): void
    {
        Notification::fake();
        $this->post('/share-your-story', $this->payload(['name' => 'Bilal', 'gender' => 'male']));
        $t = Testimonial::firstOrFail();
        $admin = $this->staff();

        Cache::forget('sitemap');
        $this->get('/sitemap.xml'); // cached before approval

        Livewire::test(ManageTestimonials::class)->assertSuccessful()
            ->callTableAction('edit', $t, ['name' => 'Bilal', 'story' => str_repeat('I fixed a spelling mistake and kept my words. ', 6), 'quote' => 'I applied early and it worked.', 'review_status' => 'pending', 'consent_given' => true, 'consent_date' => now()->toDateString(), 'country' => 'Pakistan', 'university' => 'Tsinghua University', 'gender' => 'male'])
            ->assertHasNoTableActionErrors();
        $this->assertFalse($t->fresh()->published, 'edited but still waiting');

        Livewire::test(ManageTestimonials::class)->callAction(TestAction::make('approve')->table($t));
        $t->refresh();
        $this->assertSame('approved', $t->review_status);
        $this->assertTrue($t->published);
        $this->assertSame($admin->id, $t->reviewed_by);
        $this->assertNotNull($t->reviewed_at);

        auth()->logout();
        $this->get('/stories')->assertOk()->assertSee('Bilal')->assertSee('Tsinghua University')->assertSee('images/avatars/male.svg', false)->assertSee('I applied early and it worked.');
        $this->get('/stories/'.$t->slug)->assertOk()->assertSee('Country:')->assertSee('Pakistan')->assertSee('University:')->assertSee('I fixed a spelling mistake')->assertSee('Share this post', false);
        $this->get('/')->assertSee('What students say')->assertSee('Bilal')->assertSee('Read the full story');
        $this->assertStringContainsString('/stories/'.$t->slug.'</loc>', $this->get('/sitemap.xml')->getContent());
        $this->get('/stories/'.$t->slug)->assertDontSee('amina@example.test')->assertDontSee('Amina@Example.test');
    }

    public function test_a_story_nobody_approved_cannot_be_published_and_a_rejected_one_stays_hidden(): void
    {
        $t = Testimonial::create(['name' => 'X', 'quote' => 'q', 'story' => str_repeat('s', 250), 'slug' => 'x-aaaaaa', 'source' => 'submitted', 'review_status' => 'pending', 'consent_given' => true, 'consent_date' => now()->toDateString(), 'published' => true]);
        $this->assertFalse($t->fresh()->published, 'pending can never be public, whatever the flag says');

        $this->staff();
        Livewire::test(ManageTestimonials::class)->callAction(TestAction::make('reject')->table($t), ['note' => 'Contains private details']);
        $t->refresh();
        $this->assertSame('rejected', $t->review_status);
        $this->assertSame('Contains private details', $t->review_note);
        $this->assertFalse($t->published);
        auth()->logout();
        $this->get('/stories/x-aaaaaa')->assertNotFound();
        $this->get('/stories')->assertDontSee('x-aaaaaa');
    }

    public function test_only_people_who_manage_the_website_can_review_and_deleting_a_story_removes_its_photo(): void
    {
        $this->post('/share-your-story', $this->payload(['photo' => UploadedFile::fake()->image('me.jpg', 700, 700)]));
        $t = Testimonial::firstOrFail();
        $path = $t->photo_path;

        $this->staff(Role::Advisor);
        $this->assertFalse(TestimonialResource::canViewAny());

        auth()->logout();
        $this->staff(Role::Administrator);
        $this->assertTrue(TestimonialResource::canViewAny());
        $this->assertSame('1', TestimonialResource::getNavigationBadge(), 'the menu shows how many stories wait');

        $t->delete();
        Storage::disk('public_media')->assertMissing($path);
    }
}
