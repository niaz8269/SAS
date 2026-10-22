<?php

namespace Tests\Feature\Public;

use App\Livewire\ScholarshipDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsScholarships;
use Tests\TestCase;

class DeadlineRangeTest extends TestCase
{
    use BuildsScholarships, RefreshDatabase;

    public function test_range_matches_exact_and_date_only_deadlines_inside_it_and_nothing_else(): void
    {
        $this->travelTo('2027-01-01 00:00:00');
        $exactIn = $this->publishedCycle([], ['date_type' => 'exact', 'local_closes_at' => '2027-02-10 23:30', 'source_timezone' => 'UTC']);
        $exactOut = $this->publishedCycle([], ['date_type' => 'exact', 'local_closes_at' => '2027-04-10 12:00', 'source_timezone' => 'UTC']);
        $dateIn = $this->publishedCycle([], ['date_type' => 'date_only', 'closes_on' => '2027-02-28']);
        $dateOut = $this->publishedCycle([], ['date_type' => 'date_only', 'closes_on' => '2027-06-01']);
        $rolling = $this->publishedCycle([], ['date_type' => 'rolling']);
        foreach ([$exactIn, $exactOut, $dateIn, $dateOut, $rolling] as $i => $c) {
            $c->programme->update(['title' => "Range case $i"]);
        }

        Livewire::test(ScholarshipDirectory::class)->set('deadline_from', '2027-02-01')->set('deadline_to', '2027-02-28')
            ->assertSee('Range case 0')->assertSee('Range case 2')
            ->assertDontSee('Range case 1')->assertDontSee('Range case 3')->assertDontSee('Range case 4');

        Livewire::test(ScholarshipDirectory::class)->set('deadline_from', '2027-03-01')
            ->assertSee('Range case 1')->assertSee('Range case 3')->assertDontSee('Range case 0')->assertDontSee('Range case 2');

        Livewire::test(ScholarshipDirectory::class)->set('deadline_to', '2027-02-10')
            ->assertSee('Range case 0')->assertDontSee('Range case 1')->assertDontSee('Range case 2');
    }
}
