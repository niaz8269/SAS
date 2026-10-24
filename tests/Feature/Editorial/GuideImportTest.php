<?php

namespace Tests\Feature\Editorial;

use App\Enums\Role;
use App\Filament\Resources\Countries\Pages\ManageCountries;
use App\Models\Country;
use App\Models\User;
use Database\Seeders\TaxonomySeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GuideImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TaxonomySeeder::class);
    }

    private function run_(array $entries, array $options = []): int
    {
        $path = tempnam(sys_get_temp_dir(), 'guide').'.json';
        file_put_contents($path, json_encode($entries));

        return $this->artisan('sas:import-guides', ['files' => [$path]] + $options)->run();
    }

    private function guide(string $code, array $over = []): array
    {
        return array_merge([
            'code' => $code, 'checked_on' => '2026-10-24', 'summary' => 'A plain introduction.', 'official_languages' => ['English'],
            'study_guidance' => '<h3>Studying here</h3><p>'.str_repeat('Plain official information about studying, applying and living costs. ', 6).'</p><script>alert(1)</script><ul><li><a href="https://example.gov/visa" onclick="x()">Visa</a></li></ul>',
            'cost_notes' => [['label' => 'Tuition', 'amount_text' => '10,000 to 20,000', 'currency' => 'eur', 'updated_on' => '2026-10-24'], ['label' => '', 'amount_text' => 'x']],
            'sources' => [['label' => 'Study portal', 'url' => 'https://study.example.gov/'], ['label' => 'Visa authority', 'url' => 'https://immigration.example.gov/students'], ['label' => 'Bad', 'url' => 'http://localhost/x']],
        ], $over);
    }

    public function test_a_guide_is_stored_cleaned_and_sourced_but_never_made_public(): void
    {
        $country = Country::where('code', 'AU')->firstOrFail();
        $country->forceFill(['is_destination' => true, 'guidance_published' => false])->save();

        $this->assertSame(0, $this->run_([$this->guide('AU')]));

        $country->refresh();
        $this->assertStringContainsString('Studying here', $country->study_guidance);
        $this->assertStringNotContainsString('<script', $country->study_guidance);
        $this->assertStringNotContainsString('onclick', $country->study_guidance);
        $this->assertSame(['https://study.example.gov/', 'https://immigration.example.gov/students'], array_column($country->guidance_sources, 'url'), 'only public https addresses are kept');
        $this->assertSame([['label' => 'Tuition', 'amount_text' => '10,000 to 20,000', 'currency' => 'EUR', 'updated_on' => '2026-10-24']], $country->cost_notes);
        $this->assertNull($country->official_languages, 'languages are not taken from guide files');
        $this->assertSame('2026-10-24', $country->guidance_reviewed_at->toDateString());
        $this->assertFalse((bool) $country->guidance_published, 'a guide is never made public by an import');
        $this->get(route('destinations.show', $country))->assertNotFound();
    }

    public function test_a_published_guide_stays_published_and_keeps_its_old_official_links(): void
    {
        $country = Country::where('code', 'DE')->firstOrFail();
        $country->forceFill(['is_destination' => true, 'guidance_published' => true, 'guidance_sources' => [['label' => 'DAAD', 'url' => 'https://www.daad.de/en/']]])->save();

        $this->run_([$this->guide('DE')]);

        $country->refresh();
        $this->assertTrue($country->guidance_published);
        $this->assertContains('https://www.daad.de/en/', array_column($country->guidance_sources, 'url'));
        $this->get(route('destinations.show', $country))->assertOk()->assertSee('Studying here');
    }

    public function test_a_guide_without_enough_official_links_or_text_is_not_stored(): void
    {
        $country = Country::where('code', 'CA')->firstOrFail();
        $country->forceFill(['is_destination' => true])->save();

        $this->run_([$this->guide('CA', ['sources' => [['label' => 'One', 'url' => 'https://study.example.gov/']]]), $this->guide('FR', ['study_guidance' => '<p>Too short.</p>'])]);

        $this->assertNull($country->refresh()->study_guidance);
    }

    public function test_a_dry_run_changes_nothing(): void
    {
        $country = Country::where('code', 'AU')->firstOrFail();
        $country->forceFill(['is_destination' => true])->save();

        $this->run_([$this->guide('AU')], ['--dry-run' => true]);

        $this->assertNull($country->refresh()->study_guidance);
    }

    public function test_staff_show_only_guides_that_have_been_reviewed_and_can_hide_them_again(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->role(Role::SuperAdmin)->create());
        $reviewed = Country::where('code', 'CA')->firstOrFail();
        $reviewed->forceFill(['is_destination' => true, 'study_guidance' => '<p>Guide</p>', 'guidance_reviewed_at' => '2026-10-24', 'guidance_published' => false])->save();
        $empty = Country::where('code', 'AU')->firstOrFail();
        $empty->forceFill(['is_destination' => true, 'guidance_published' => false])->save();

        Livewire::test(ManageCountries::class)->callTableBulkAction('showGuides', [$reviewed, $empty]);
        $this->assertTrue((bool) $reviewed->refresh()->guidance_published);
        $this->assertFalse((bool) $empty->refresh()->guidance_published, 'a country without a reviewed guide is skipped');

        Livewire::test(ManageCountries::class)->callTableBulkAction('hideGuides', [$reviewed]);
        $this->assertFalse((bool) $reviewed->refresh()->guidance_published);
    }
}
