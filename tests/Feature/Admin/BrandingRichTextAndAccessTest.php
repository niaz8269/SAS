<?php

namespace Tests\Feature\Admin;

use App\Domain\Site\Site;
use App\Enums\Role;
use App\Filament\Pages\SiteSettings;
use App\Filament\Resources\ScholarshipCycles\Pages\EditScholarshipCycle;
use App\Filament\Resources\Universities\Pages\ManageUniversities;
use App\Models\University;
use App\Models\User;
use App\Support\Html;
use Database\Seeders\TaxonomySeeder;
use Filament\Facades\Filament;
use Filament\Forms\Components\RichEditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsScholarships;
use Tests\TestCase;

class BrandingRichTextAndAccessTest extends TestCase
{
    use BuildsScholarships, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TaxonomySeeder::class);
        Filament::setCurrentPanel('admin');
    }

    private function staff(Role $role): User
    {
        $user = User::factory()->role($role)->create();
        $user->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP', 'app_authentication_recovery_codes' => ['aaaa-bbbb']])->save();
        $this->actingAs($user);

        return $user;
    }

    public function test_the_super_admin_can_open_every_screen_of_the_admin_panel(): void
    {
        $this->staff(Role::SuperAdmin);

        $closed = [];
        foreach (Filament::getResources() as $resource) {
            $resource::canViewAny() || $closed[] = $resource;
        }
        foreach (Filament::getPages() as $page) {
            $page::canAccess() || $closed[] = $page;
        }

        $this->assertSame([], $closed, 'the super admin must reach every resource and page');
    }

    public function test_the_site_settings_page_has_logo_favicon_share_picture_and_contact_details(): void
    {
        $this->staff(Role::SuperAdmin);

        Livewire::test(SiteSettings::class)->assertSuccessful()
            ->assertFormFieldExists('logo_path')->assertFormFieldExists('favicon_path')->assertFormFieldExists('og_image_path')
            ->assertFormFieldExists('contact_email')->assertFormFieldExists('contact_phone')->assertFormFieldExists('contact_whatsapp')
            ->assertFormFieldExists('contact_wechat')->assertFormFieldExists('contact_address')->assertFormFieldExists('whatsapp_message')
            ->assertFormFieldExists('social_facebook');
    }

    public function test_a_chosen_favicon_share_picture_and_whatsapp_message_are_used_on_every_layout(): void
    {
        config(['sas.contact_whatsapp' => '+86 176 4041 2827']);
        Site::save(['favicon_path' => 'brand/fav.png', 'og_image_path' => 'brand/share.jpg', 'whatsapp_message' => 'Hi team, I need a scholarship']);
        $fav = Site::mediaUrl('favicon_path');
        $this->assertStringEndsWith('brand/fav.png', $fav);

        foreach (['/', '/about', '/login'] as $path) {
            $this->get($path)->assertOk()->assertSee('<link rel="icon" href="'.$fav.'">', false)->assertDontSee('favicon-32.png', false);
        }
        $this->get('/about')->assertSee(Site::mediaUrl('og_image_path'), false)->assertDontSee('og-default.jpg', false)
            ->assertSee('text=Hi%20team%2C%20I%20need%20a%20scholarship', false);
        $this->actingAs(User::factory()->create())->get('/student')->assertSee('<link rel="icon" href="'.$fav.'">', false);

        // nothing chosen: the bundled files
        Site::save(['favicon_path' => null, 'og_image_path' => null]);
        auth()->logout();
        $this->get('/about')->assertSee('favicon-32.png', false)->assertSee('og-default.jpg', false);
    }

    public function test_long_texts_are_cleaned_when_shown_and_plain_words_are_used_for_descriptions(): void
    {
        $cycle = $this->publishedCycle([
            'slug' => '2026-intake',
            'summary' => '<p>Covers <strong>full tuition</strong>.</p><script>alert(1)</script><ul><li>Living costs</li></ul>',
            'eligibility_notes' => "First line\n\nSecond paragraph <script>x</script>",
        ]);
        University::create(['name' => 'Rich University', 'slug' => 'rich-university', 'published' => true, 'csc_key' => 'r1', 'summary' => '<p>A <em>friendly</em> campus.</p><img src="x" onerror="alert(1)">']);

        $url = '/scholarships/'.$cycle->programme->slug.'/2026-intake';
        $this->get($url)->assertOk()->assertSee('<strong>full tuition</strong>', false)->assertSee('<li>Living costs</li>', false)
            ->assertSee('Second paragraph', false)->assertDontSee('alert(1)', false)->assertDontSee('<script>x</script>', false)
            ->assertSee('content="Covers full tuition. Living costs"', false);
        $this->get('/universities/rich-university')->assertOk()->assertSee('<em>friendly</em>', false)->assertDontSee('onerror', false);

        $this->assertSame('Covers full tuition. Living costs', Html::text('<p>Covers <strong>full tuition</strong>.</p><ul><li>Living costs</li></ul>'));
        $this->assertSame('One<br>', Html::text('One', 20).'<br>');
        // older plain text is shown as paragraphs, and markup in it is shown as words, not run
        $this->assertSame("<p>One</p><p>Two<br>\nthree</p>", (string) Html::rich("One\n\nTwo\nthree"));
        $this->assertSame('', (string) Html::rich('  '));
    }

    public function test_the_university_and_scholarship_forms_use_the_rich_editor(): void
    {
        $this->staff(Role::Administrator);
        $uni = University::create(['name' => 'Form University', 'slug' => 'form-university', 'published' => true, 'csc_key' => 'f1']);
        $cycle = $this->publishedCycle(['slug' => '2026-intake']);

        $is = fn ($field) => $field instanceof RichEditor;
        Livewire::test(ManageUniversities::class)->callTableAction('edit', $uni, ['summary' => '<p>Saved <strong>with</strong> bold</p>']);
        $this->assertSame('<p>Saved <strong>with</strong> bold</p>', $uni->fresh()->summary);
        Livewire::test(EditScholarshipCycle::class, ['record' => $cycle->getRouteKey()])->assertSuccessful()
            ->assertFormFieldExists('summary', $is)->assertFormFieldExists('funding_summary', $is)->assertFormFieldExists('eligibility_notes', $is)
            ->assertFormFieldExists('programmes_note', $is);
    }
}
