<?php

namespace App\Livewire;

use App\Domain\Scholarship\ScholarshipSearch;
use App\Models\AnalyticsEvent;
use App\Models\Country;
use App\Models\DegreeLevel;
use App\Models\Subject;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** PUB-03 / DIR-02: every filter, the sort and the page live in the URL so a shared link recreates the results. */
class ScholarshipDirectory extends Component
{
    use WithPagination;

    #[Url(as: 'q', history: true)]
    public string $q = '';

    #[Url(history: true)]
    public array $destination = [];

    #[Url(history: true)]
    public array $nationality = [];

    #[Url(history: true)]
    public array $degree = [];

    #[Url(history: true)]
    public array $subject = [];

    #[Url(history: true)]
    public array $funding = [];

    #[Url(history: true)]
    public array $language = [];

    #[Url(history: true)]
    public array $mode = [];

    #[Url(history: true)]
    public array $intake = [];

    #[Url(history: true)]
    public array $status = [];

    #[Url(history: true)]
    public string $deadline_from = '';

    #[Url(history: true)]
    public string $deadline_to = '';

    #[Url(history: true)]
    public bool $show_closed = false;

    #[Url(history: true)]
    public string $sort = 'relevance';

    public function updated(string $property): void
    {
        $this->resetPage();
    }

    private const LISTS = ['destination', 'nationality', 'degree', 'subject', 'funding', 'language', 'mode', 'intake', 'status'];

    public function filters(): array
    {
        return $this->only(['q', 'sort', 'show_closed', 'deadline_from', 'deadline_to', ...self::LISTS]);
    }

    public function clearAll(): void
    {
        $this->reset(['q', 'deadline_from', 'deadline_to', 'show_closed', ...self::LISTS]);
        $this->resetPage();
    }

    public function removeFilter(string $key, string $value = ''): void
    {
        if (in_array($key, self::LISTS, true)) {
            $this->{$key} = array_values(array_diff($this->{$key}, [$value]));
        } elseif (in_array($key, ['q', 'deadline_from', 'deadline_to'], true)) {
            $this->{$key} = '';
        } elseif ($key === 'show_closed') {
            $this->show_closed = false;
        }
        $this->resetPage();
    }

    /** @return list<array{key:string,value:string,label:string}> */
    public function activeFilters(array $labels): array
    {
        $out = [];
        if (trim($this->q) !== '') {
            $out[] = ['key' => 'q', 'value' => '', 'label' => 'Keyword: '.$this->q];
        }
        foreach (self::LISTS as $key) {
            foreach (array_filter((array) $this->{$key}, fn ($v) => $v !== '') as $v) {
                $out[] = ['key' => $key, 'value' => (string) $v, 'label' => ($labels[$key][$v] ?? $v)];
            }
        }
        if ($this->deadline_from) {
            $out[] = ['key' => 'deadline_from', 'value' => '', 'label' => 'Closing after '.$this->deadline_from];
        }
        if ($this->deadline_to) {
            $out[] = ['key' => 'deadline_to', 'value' => '', 'label' => 'Closing before '.$this->deadline_to];
        }
        if ($this->show_closed) {
            $out[] = ['key' => 'show_closed', 'value' => '', 'label' => 'Including closed calls'];
        }

        return $out;
    }

    public function render(ScholarshipSearch $search)
    {
        $results = $search->paginate($this->filters(), 12);

        if ($results->total() === 0 && ($this->q !== '' || $this->destination || $this->degree)) {
            AnalyticsEvent::create(['name' => 'zero_result_search', 'meta' => ['filters' => count($this->activeFilters([]))]]); // no free text
        }

        $labels = [
            'destination' => Country::destinations()->pluck('name', 'slug')->all(),
            'nationality' => Country::orderBy('name')->pluck('name', 'slug')->all(),
            'degree' => DegreeLevel::orderBy('sort')->pluck('name', 'slug')->all(),
            'subject' => Subject::orderBy('name')->pluck('name', 'slug')->all(),
            'funding' => ['full' => 'Fully funded (verified)', 'partial' => 'Partial funding', 'tuition_only' => 'Tuition only', 'stipend_only' => 'Stipend only'],
            'mode' => ['on_campus' => 'On campus', 'online' => 'Online', 'hybrid' => 'Hybrid'],
            'status' => \App\Enums\ApplicationStatus::options(),
            'language' => [], 'intake' => [],
        ];

        return view('livewire.scholarship-directory', [
            'results' => $results,
            'labels' => $labels,
            'active' => $this->activeFilters($labels),
            'languages' => \App\Models\ScholarshipCycle::visible()->whereNotNull('teaching_language')->distinct()->orderBy('teaching_language')->pluck('teaching_language'),
            'intakes' => \App\Models\ScholarshipCycle::visible()->distinct()->orderBy('intake_year')->pluck('intake_year'),
        ]);
    }
}