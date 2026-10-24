<div x-data="{ filtersOpen: false }">
    <div class="grid gap-6 lg:grid-cols-[17.5rem_1fr]">
        {{-- Filters --}}
        <aside aria-label="Filters">
            <button type="button" class="btn-outline w-full lg:hidden" @click="filtersOpen = !filtersOpen" :aria-expanded="filtersOpen.toString()" aria-controls="filter-panel">
                Filters @if (count($active)) ({{ count($active) }}) @endif
            </button>
            <form id="filter-panel" class="card mt-3 space-y-5 p-4 lg:mt-0 lg:block" :class="filtersOpen ? 'block' : 'hidden lg:block'" wire:submit.prevent>
                <div>
                    <label for="f-q" class="label">Keyword</label>
                    <input id="f-q" type="search" wire:model.live.debounce.400ms="q" class="input" placeholder="Title, university, funder, subject" autocomplete="off">
                </div>

                @foreach ([
                    ['destination', 'Destination', $labels['destination']],
                    ['degree', 'Degree level', $labels['degree']],
                    ['subject', 'Subject', $labels['subject']],
                    ['funding', 'Funding', $labels['funding']],
                    ['mode', 'Delivery', $labels['mode']],
                ] as [$key, $title, $options])
                    <fieldset>
                        <legend class="label">{{ $title }}</legend>
                        @if (count($options))
                            <div class="max-h-48 space-y-1 overflow-y-auto pr-1">
                                @foreach ($options as $value => $label)
                                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" value="{{ $value }}" wire:model.live="{{ $key }}" class="rounded border-slate-300 text-navy-700"> {{ $label }}</label>
                                @endforeach
                            </div>
                        @else
                            <p class="text-xs text-muted">No options yet.</p>
                        @endif
                    </fieldset>
                @endforeach

                <div>
                    <label for="f-nat" class="label">Your nationality</label>
                    <select id="f-nat" wire:model.live="nationality.0" class="input">
                        <option value="">Any</option>
                        @foreach ($labels['nationality'] as $slug => $name)<option value="{{ $slug }}">{{ $name }}</option>@endforeach
                    </select>
                    <p class="field-hint">Shows calls open to any nationality or that list yours. Calls with unconfirmed eligibility are not matched.</p>
                    @if (empty($nationality[0]) && ($mine = $this->myNationality()))<button type="button" wire:click="useMyNationality" class="mt-1 text-sm font-semibold text-navy-700 underline">Use my nationality ({{ $mine->name }})</button>@endif
                </div>

                @if ($languages->isNotEmpty())
                    <fieldset>
                        <legend class="label">Teaching language</legend>
                        @foreach ($languages as $lang)
                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" value="{{ $lang }}" wire:model.live="language" class="rounded border-slate-300 text-navy-700"> {{ $lang }}</label>
                        @endforeach
                    </fieldset>
                @endif

                @if ($intakes->isNotEmpty())
                    <fieldset>
                        <legend class="label">Intake year</legend>
                        <div class="flex flex-wrap gap-3">
                            @foreach ($intakes as $y)
                                <label class="flex items-center gap-2 text-sm"><input type="checkbox" value="{{ $y }}" wire:model.live="intake" class="rounded border-slate-300 text-navy-700"> {{ $y }}</label>
                            @endforeach
                        </div>
                    </fieldset>
                @endif

                <fieldset>
                    <legend class="label">Application status</legend>
                    <div class="space-y-2">
                    @foreach (['open' => 'Open', 'upcoming' => 'Upcoming', 'rolling' => 'Rolling', 'deadline_unconfirmed' => 'Deadline unconfirmed'] as $v => $l)
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" value="{{ $v }}" wire:model.live="status" class="rounded border-slate-300 text-navy-700"> {{ $l }}</label>
                    @endforeach
                    </div>
                </fieldset>

                <fieldset>
                    <legend class="label">Closing between</legend>
                    <div class="grid grid-cols-2 gap-2">
                        <div><label for="f-from" class="sr-only">From</label><input id="f-from" type="date" wire:model.live="deadline_from" class="input"></div>
                        <div><label for="f-to" class="sr-only">To</label><input id="f-to" type="date" wire:model.live="deadline_to" class="input"></div>
                    </div>
                </fieldset>

                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model.live="show_closed" class="rounded border-slate-300 text-navy-700"> Include closed calls</label>

                <button type="button" wire:click="clearAll" class="btn-ghost w-full">Clear all filters</button>
            </form>
        </aside>

        {{-- Results --}}
        <section aria-labelledby="results-heading" aria-live="polite">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <h2 id="results-heading" class="text-lg font-bold">
                    {{ number_format($results->total()) }} {{ \Illuminate\Support\Str::plural('scholarship', $results->total()) }}
                </h2>
                <div>
                    <label for="f-sort" class="sr-only">Sort by</label>
                    <select id="f-sort" wire:model.live="sort" class="input !w-auto">
                        <option value="relevance">Relevance</option>
                        <option value="closing">Nearest confirmed closing time</option>
                        <option value="newest">Newest approved cycle</option>
                        <option value="verified">Recently verified</option>
                    </select>
                </div>
            </div>

            @if (count($active))
                <ul class="mt-3 flex flex-wrap gap-2" aria-label="Active filters">
                    @foreach ($active as $f)
                        <li><button type="button" wire:click="removeFilter('{{ $f['key'] }}', @js($f['value']))" class="badge-green !py-1" aria-label="Remove filter {{ $f['label'] }}">{{ $f['label'] }} <span aria-hidden="true">&times;</span></button></li>
                    @endforeach
                    <li><button type="button" wire:click="clearAll" class="text-sm text-navy-700 underline">Clear all</button></li>
                </ul>
            @endif

            <div wire:loading.delay class="mt-4 grid gap-4 sm:grid-cols-2" aria-hidden="true">
                @for ($i = 0; $i < 4; $i++)<div class="skeleton h-56"></div>@endfor
            </div>

            <div wire:loading.remove class="mt-4">
                @if ($results->isEmpty())
                    <div class="card p-8 text-center">
                        <h3 class="text-lg font-bold">No scholarships match these filters</h3>
                        <p class="mt-2 text-sm text-muted">Try removing a filter, or tell us what you need and our team can look into it for free.</p>
                        <div class="mt-4 flex flex-wrap justify-center gap-3">
                            <button type="button" wire:click="clearAll" class="btn-outline">Clear all filters</button>
                            <a href="{{ route('contact', ['category' => 'advisory']) }}" class="btn-primary">Ask us (free enquiry)</a>
                        </div>
                    </div>
                @else
                    <div class="grid gap-4 sm:grid-cols-2">
                        @foreach ($results as $cycle)
                            <x-cycle-card :cycle="$cycle" wire:key="cycle-{{ $cycle->id }}" />
                        @endforeach
                    </div>
                    <div class="mt-6">{{ $results->links() }}</div>
                @endif
            </div>
        </section>
    </div>
</div>