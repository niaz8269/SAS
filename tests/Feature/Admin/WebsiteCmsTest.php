<?php

namespace Tests\Feature\Admin;

use App\Domain\Site\Site;
use App\Enums\Role;
use App\Filament\Pages\SiteSettings;
use App\Filament\Resources\CmsPages\Pages\ManageCmsPages;
use App\Filament\Resources\HomeSections\HomeSectionResource;
use App\Filament\Resources\HomeSections\Pages\ManageHomeSections;
use App\Filament\Resources\MenuItems\Pages\ManageMenuItems;
use App\Models\CmsPage;
use App\Models\HomeSection;
use App\Models\MenuItem;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WebsiteCmsTest extends TestCase
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

    public function test_defaults_are_seeded_so_the_site_works_before_anything_is_edited(): void
    {
        $this->assertSame(13, HomeSection::count());
        $this->assertGreaterThanOrEqual(7, MenuItem::where('location', 'header')->count());
        $this->get('/')->assertOk()->assertSee('A world of possibilities.')->assertSee('How the platform works')->assertSee('Scholarships');
    }

    public function test_site_settings_change_the_brand_footer_and_announcement_on_the_public_site(): void
    {
        $this->staff();
        Livewire::test(SiteSettings::class)
            ->fillForm(['brand_name' => 'ACME Scholarships', 'contact_email' => 'help@acme.test', 'contact_phone' => '+86 10 0000 0000', 'footer_note' => 'Extra footer line.',
                'announcement_enabled' => true, 'announcement_text' => 'Applications open soon', 'announcement_url' => '/scholarships'])
            ->call('save')->assertHasNoFormErrors();

        $this->get('/')->assertOk()->assertSee('ACME')->assertSee('help@acme.test')->assertSee('+86 10 0000 0000')
            ->assertSee('Extra footer line.')->assertSee('Applications open soon');
        $this->assertDatabaseHas('audits', ['action' => 'site.settings_saved']);
    }

    public function test_announcement_link_rejects_script_urls(): void
    {
        $this->staff();
        Livewire::test(SiteSettings::class)->fillForm(['announcement_url' => 'javascript:alert(1)'])->call('save')->assertHasFormErrors(['announcement_url']);
        $this->assertSame('#', Site::href('javascript:alert(1)'));
        $this->assertSame('#', Site::href('//evil.example'));
        $this->assertSame('/about', Site::href('/about'));
    }

    public function test_homepage_text_can_be_edited_hidden_and_extended(): void
    {
        $this->staff();
        $hero = HomeSection::where('key', 'hero')->first();
        Livewire::test(ManageHomeSections::class)
            ->callAction(TestAction::make('edit')->table($hero), ['heading' => 'Study in China made simple', 'label' => 'Hero and search box', 'enabled' => true])
            ->assertHasNoFormErrors();
        $this->get('/')->assertSee('Study in China made simple')->assertDontSee('A world of possibilities.');

        HomeSection::where('key', 'how')->update(['enabled' => false]);
        \Cache::forget('home.sections');
        $this->get('/')->assertDontSee('How the platform works');

        Livewire::test(ManageHomeSections::class)
            ->callAction('create', ['label' => 'Scholarship deadlines FAQ', 'heading' => 'Our promise', 'body' => 'We never invent a closing time.', 'enabled' => true]);
        $this->get('/')->assertSee('Our promise')->assertSee('We never invent a closing time.');
        $this->assertSame(1, HomeSection::where('key', 'like', 'custom-%')->count());
    }

    public function test_built_in_sections_cannot_be_deleted_but_added_ones_can(): void
    {
        $this->staff();
        $builtIn = HomeSection::where('key', 'hero')->first();
        $custom = HomeSection::create(['key' => 'custom-abc', 'label' => 'Extra', 'heading' => 'Extra', 'sort' => 999]);
        $this->assertFalse(HomeSectionResource::canDelete($builtIn));
        $this->assertTrue(HomeSectionResource::canDelete($custom));
    }

    public function test_menu_links_can_be_added_hidden_and_deleted(): void
    {
        $this->staff();
        Livewire::test(ManageMenuItems::class)
            ->callAction('create', ['location' => 'header', 'label' => 'Apply now', 'url' => '/consultation', 'visible' => true])->assertHasNoFormErrors()
            ->callAction('create', ['location' => 'header', 'label' => 'Bad', 'url' => 'javascript:alert(1)', 'visible' => true])->assertHasFormErrors(['url']);
        $this->get('/')->assertSee('Apply now');

        $link = MenuItem::where('label', 'Apply now')->first();
        $link->update(['visible' => false]);
        $this->get('/')->assertDontSee('Apply now');

        $about = MenuItem::where('location', 'header')->where('label', 'About')->first();
        $about->delete();
        $this->get('/')->assertOk();
        $this->assertDatabaseMissing('menu_items', ['id' => $about->id]);
    }

    public function test_cms_page_replaces_the_built_in_page_only_when_published_and_is_sanitised(): void
    {
        $this->staff();
        Livewire::test(ManageCmsPages::class)->callAction('create', [
            'title' => 'About our team', 'slug' => 'about', 'published' => false,
            'body' => '<p>Hello <strong>world</strong></p><script>alert(1)</script>',
        ])->assertHasNoFormErrors();

        $this->get('/about')->assertOk()->assertSee('How we keep listings trustworthy')->assertDontSee('About our team');

        CmsPage::where('slug', 'about')->update(['published' => true]);
        $this->get('/about')->assertOk()->assertSee('About our team')->assertSee('Hello')->assertDontSee('<script>alert(1)</script>', false);

        CmsPage::create(['slug' => 'visa-faq', 'title' => 'Visa FAQ', 'body' => '<p>Visa answers</p>', 'published' => true]);
        $this->get('/pages/visa-faq')->assertOk()->assertSee('Visa answers');
        $this->get('/pages/nope')->assertNotFound();
        CmsPage::create(['slug' => 'secret', 'title' => 'Secret', 'published' => false]);
        $this->get('/pages/secret')->assertNotFound();
        $this->get('/sitemap.xml')->assertSee('/pages/visa-faq', false)->assertDontSee('/pages/secret', false);
    }

    public function test_only_staff_with_the_site_permission_can_manage_the_website(): void
    {
        $this->staff(Role::Advisor);
        $this->get('/admin/site-settings')->assertForbidden();
        $this->get('/admin/home-sections')->assertForbidden();
        $this->get('/admin/menu-items')->assertForbidden();
        $this->get('/admin/cms-pages')->assertForbidden();
    }

    public function test_administrator_can_open_all_website_screens(): void
    {
        $this->staff(Role::Administrator);
        foreach (['site-settings', 'home-sections', 'menu-items', 'cms-pages'] as $path) {
            $this->get('/admin/'.$path)->assertOk();
        }
    }

    public function test_dashboard_shows_content_and_activity_widgets_without_a_role_warning(): void
    {
        $this->staff(Role::SuperAdmin);
        $this->get('/admin')->assertOk()->assertSee('Manage your website')->assertSee('Website content')->assertSee('Recent activity')->assertSee('Testimonials');

        $this->staff(Role::Administrator);
        $this->get('/admin')->assertOk()->assertDontSee('This role cannot edit the website itself');

        $this->staff(Role::Advisor);
        $this->get('/admin')->assertOk()->assertDontSee('Manage your website')->assertDontSee('This role cannot edit the website itself'); // nothing to manage: no card
    }
}
