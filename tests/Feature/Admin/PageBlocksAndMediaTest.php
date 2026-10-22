<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Filament\Resources\MediaFiles\Pages\ManageMediaFiles;
use App\Filament\Resources\PageBlocks\PageBlockResource;
use App\Filament\Resources\PageBlocks\Pages\ManagePageBlocks;
use App\Models\HomeSection;
use App\Models\MediaFile;
use App\Models\PageBlock;
use App\Models\User;
use App\Support\Html;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PageBlocksAndMediaTest extends TestCase
{
    use RefreshDatabase;

    private function staff(Role $role = Role::SuperAdmin): User
    {
        Filament::setCurrentPanel('admin');
        $user = User::factory()->role($role)->create();
        $user->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP', 'app_authentication_recovery_codes' => ['aaaa-bbbb']])->save();
        $this->actingAs($user);

        return $user;
    }

    public function test_every_list_page_still_shows_its_original_wording_by_default(): void
    {
        $this->get('/services')->assertOk()->assertSee('Support beyond the scholarship');
        $this->get('/destinations')->assertOk()->assertSee('Study destinations')->assertSee('Destination guides will appear here');
        $this->get('/resources')->assertOk()->assertSee('Practical guides for international students.');
        $this->get('/contact')->assertOk()->assertSee('Contact us')->assertSee('Other ways to reach us')->assertSee('Request a consultation');
        $this->get('/scholarships')->assertOk()->assertSee('Scholarship directory');
    }

    public function test_intro_text_and_search_description_can_be_edited(): void
    {
        $this->staff();
        $intro = PageBlock::builtIn('services', 'intro');
        Livewire::test(ManagePageBlocks::class)
            ->callAction(TestAction::make('edit')->table($intro), ['label' => $intro->label, 'heading' => 'What we can do for you', 'body' => 'Plain help, no promises.', 'subheading' => 'Meta text for search engines.', 'page' => 'services'])
            ->assertHasNoFormErrors();

        $this->get('/services')->assertSee('What we can do for you')->assertSee('Plain help, no promises.')->assertSee('Meta text for search engines.', false)
            ->assertDontSee('Support beyond the scholarship');
    }

    public function test_added_blocks_appear_above_or_below_and_can_be_hidden_or_deleted(): void
    {
        $this->staff();
        Livewire::test(ManagePageBlocks::class)
            ->callAction('create', ['page' => 'resources', 'label' => 'Visa notice', 'position' => 'top', 'enabled' => true, 'heading' => 'Visa rules change', 'body' => 'Check the embassy site.'])
            ->callAction('create', ['page' => 'resources', 'label' => 'Newsletter', 'position' => 'bottom', 'enabled' => true, 'heading' => 'Stay in touch', 'cta_label' => 'Write to us', 'cta_url' => '/contact']);

        $html = $this->get('/resources')->assertOk()->assertSee('Visa rules change')->assertSee('Stay in touch')->getContent();
        $this->assertLessThan(strpos($html, 'Practical guides'), strpos($html, 'Visa rules change'));
        $this->assertGreaterThan(strpos($html, 'Practical guides'), strpos($html, 'Stay in touch'));

        $block = PageBlock::where('label', 'Visa notice')->first();
        $block->update(['enabled' => false]);
        $this->get('/resources')->assertDontSee('Visa rules change');

        $this->assertTrue(PageBlockResource::canDelete($block));
        $this->assertFalse(PageBlockResource::canDelete(PageBlock::builtIn('resources', 'intro')));
    }

    public function test_contact_side_box_can_be_edited_and_hidden_but_the_intro_cannot(): void
    {
        $this->staff();
        PageBlock::builtIn('contact', 'aside')->update(['heading' => 'Reach the team', 'body' => 'We answer within two working days.', 'cta_label' => 'Book a call', 'cta_url' => '/consultation']);
        $this->get('/contact')->assertSee('Reach the team')->assertSee('We answer within two working days.')->assertSee('Book a call');

        PageBlock::builtIn('contact', 'aside')->update(['enabled' => false]);
        $this->get('/contact')->assertDontSee('Reach the team');

        PageBlock::builtIn('contact', 'intro')->update(['enabled' => false]);
        \Cache::forget('page.blocks');
        $this->get('/contact')->assertSee('Contact us'); // the page title is never hidden
    }

    public function test_media_upload_registers_a_library_entry_and_deleting_removes_the_file(): void
    {
        Storage::fake('public_media');
        $this->staff();
        Livewire::test(ManageMediaFiles::class)
            ->callAction('upload', ['files' => [UploadedFile::fake()->image('hero.png', 200, 100)], 'original_names' => ['hero.png']])
            ->assertHasNoFormErrors();

        $media = MediaFile::firstOrFail();
        $this->assertSame('hero', $media->name);
        $this->assertTrue($media->isImage());
        Storage::disk('public_media')->assertExists($media->path);

        Livewire::test(ManageMediaFiles::class)->callAction(TestAction::make('delete')->table($media));
        Storage::disk('public_media')->assertMissing($media->path);
        $this->assertDatabaseCount('media_files', 0);
    }

    public function test_library_image_shows_on_the_homepage_hero_and_header_logo(): void
    {
        Storage::fake('public_media');
        Storage::disk('public_media')->put('media/hero.png', 'x');
        $media = MediaFile::register('media/hero.png', 'hero');
        HomeSection::where('key', 'hero')->update(['image_path' => $media->path]);
        \Cache::forget('home.sections');

        $this->get('/')->assertOk()->assertSee('media/hero.png', false)->assertDontSee('Temporary illustration');
    }

    public function test_only_images_from_our_own_media_storage_survive_in_page_text(): void
    {
        $base = Storage::disk('public_media')->url('');
        $clean = Html::clean('<p>Hi</p><img src="'.$base.'media/a.png" alt="A" onerror="alert(1)"><img src="https://evil.example/x.png"><img src="javascript:alert(1)"><img src="/storage/../../.env">');
        $this->assertStringContainsString('media/a.png', $clean);
        $this->assertStringNotContainsString('evil.example', $clean);
        $this->assertStringNotContainsString('javascript', $clean);
        $this->assertStringNotContainsString('onerror', $clean);
        $this->assertStringNotContainsString('.env', $clean);
    }

    public function test_only_staff_with_the_site_permission_can_open_the_new_screens(): void
    {
        $this->staff(Role::Editor);
        $this->get('/admin/page-blocks')->assertForbidden();
        $this->get('/admin/media-files')->assertForbidden();
    }

    public function test_administrator_can_open_the_new_screens(): void
    {
        $this->staff(Role::Administrator);
        $this->get('/admin/page-blocks')->assertOk();
        $this->get('/admin/media-files')->assertOk();
    }
}
