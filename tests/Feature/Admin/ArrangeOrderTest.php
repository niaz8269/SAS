<?php

namespace Tests\Feature\Admin;

use App\Domain\Scholarship\ScholarshipSearch;
use App\Enums\Role;
use App\Filament\Pages\ArrangeScholarships;
use App\Filament\Pages\ArrangeUniversities;
use App\Models\ScholarshipCycle;
use App\Models\University;
use App\Models\User;
use Database\Seeders\TaxonomySeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsScholarships;
use Tests\TestCase;

class ArrangeOrderTest extends TestCase
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

    /** @return array<string,University> */
    private function universities(): array
    {
        $out = [];
        foreach (['Alpha', 'Bravo', 'Charlie', 'Delta'] as $i => $name) {
            $out[$name] = University::create(['name' => $name.' University', 'slug' => strtolower($name), 'published' => true, 'csc_key' => 'k'.$i]);
        }

        return $out;
    }

    private function publicNames(string $query = ''): array
    {
        preg_match_all('#<h2[^>]*>\s*<a[^>]*>([^<]+)</a>#', $this->get('/universities'.$query)->getContent(), $m);

        return array_map(fn ($n) => trim(html_entity_decode($n)), $m[1]);
    }

    public function test_an_administrator_can_send_a_university_to_the_top_the_bottom_or_a_position_and_the_website_follows(): void
    {
        $u = $this->universities();
        $this->staff(Role::Administrator);

        // automatic order: A to Z
        $this->assertSame(['Alpha University', 'Bravo University', 'Charlie University', 'Delta University'], array_slice($this->publicNames(), 0, 4));

        $page = Livewire::test(ArrangeUniversities::class)->assertSuccessful();
        $page->callTableAction('top', $u['Delta']);
        $this->assertSame(['Delta University', 'Alpha University', 'Bravo University', 'Charlie University'], array_slice($this->publicNames(), 0, 4));

        $page->callTableAction('bottom', $u['Delta'])->callTableAction('top', $u['Charlie']);
        $this->assertSame(['Charlie University', 'Alpha University', 'Bravo University', 'Delta University'], array_slice($this->publicNames(), 0, 4));

        $page->callTableAction('position', $u['Delta'], ['position' => 2]);
        $this->assertSame(['Charlie University', 'Delta University', 'Alpha University', 'Bravo University'], array_slice($this->publicNames(), 0, 4));
        $this->assertSame([1, 2, 3, 4], University::orderBy('sort_order')->pluck('sort_order')->all());

        // a visitor who chooses A to Z still gets A to Z
        $this->assertSame('Alpha University', $this->publicNames('?sort=name')[0]);

        // a new university has no position and comes after the placed ones
        University::create(['name' => 'Aardvark University', 'slug' => 'aardvark', 'published' => true, 'csc_key' => 'k9']);
        $this->assertSame('Aardvark University', $this->publicNames()[4]);
    }

    public function test_starting_to_arrange_keeps_todays_order_and_back_to_automatic_removes_positions(): void
    {
        $this->universities();
        $this->staff(Role::SuperAdmin);

        $page = Livewire::test(ArrangeUniversities::class);
        $this->assertFalse($page->instance()->isArranged());
        $page->callAction(TestAction::make('pin')->table());
        $this->assertTrue(Livewire::test(ArrangeUniversities::class)->instance()->isArranged());
        $this->assertSame(['Alpha University', 'Bravo University', 'Charlie University', 'Delta University'], array_slice($this->publicNames(), 0, 4));

        Livewire::test(ArrangeUniversities::class)->callAction(TestAction::make('reset')->table());
        $this->assertSame(0, University::whereNotNull('sort_order')->count());
    }

    public function test_dragging_rows_saves_the_new_order(): void
    {
        $u = $this->universities();
        $this->staff(Role::Administrator);
        Livewire::test(ArrangeUniversities::class)->callAction(TestAction::make('pin')->table());

        Livewire::test(ArrangeUniversities::class)
            ->set('isTableReordering', true)
            ->call('reorderTable', [$u['Bravo']->id, $u['Delta']->id, $u['Alpha']->id, $u['Charlie']->id]);

        $this->assertSame(['Bravo University', 'Delta University', 'Alpha University', 'Charlie University'], array_slice($this->publicNames(), 0, 4));
    }

    public function test_scholarships_can_be_ordered_the_same_way_and_the_directory_follows(): void
    {
        $this->staff(Role::SuperAdmin);
        $a = $this->publishedCycle(['slug' => '2026-intake']);
        $b = $this->publishedCycle(['slug' => '2026-intake']);
        $c = $this->publishedCycle(['slug' => '2026-intake']);
        foreach ([$a, $b, $c] as $i => $cycle) {
            $cycle->programme->update(['title' => ['Alpha', 'Bravo', 'Charlie'][$i].' Scholarship']);
        }
        $order = fn () => ScholarshipCycle::visible()->manualOrder()->orderByDesc('id')->pluck('id')->all();
        $this->assertSame([$c->id, $b->id, $a->id], $order());

        Livewire::test(ArrangeScholarships::class)->assertSuccessful()->callTableAction('top', $a)->callTableAction('bottom', $c);
        $this->assertSame([$a->id, $b->id, $c->id], $order());
        $this->assertSame([1, 2, 3], ScholarshipCycle::orderBy('sort_order')->pluck('sort_order')->all());

        $search = app(ScholarshipSearch::class);
        $titles = $search->query($search->clean(['sort' => 'relevance']))->get()->map(fn ($x) => $x->programme->title)->all();
        $this->assertSame(['Alpha Scholarship', 'Bravo Scholarship', 'Charlie Scholarship'], array_slice($titles, 0, 3));
    }

    public function test_only_people_with_website_rights_can_open_the_order_pages(): void
    {
        $this->staff(Role::Advisor);
        $this->assertFalse(ArrangeUniversities::canAccess());
        $this->assertFalse(ArrangeScholarships::canAccess());

        auth()->logout();
        $this->staff(Role::Administrator);
        $this->assertTrue(ArrangeUniversities::canAccess());

        auth()->logout();
        $this->staff(Role::SuperAdmin);
        $this->assertTrue(ArrangeScholarships::canAccess());
    }
}
