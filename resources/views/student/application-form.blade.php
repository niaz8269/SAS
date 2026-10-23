<x-dynamic-component :component="$ctx->layout()" :title="$plan ? 'Application form' : 'Your details'">
    @if ($plan)
        @include('student.wizard._header')
    @else
        <div class="text-center"><h1 class="text-[22px] font-normal text-[#0b6ec7]">Your details{{ $ctx->isAgent() ? ' for '.$student->name : '' }}</h1></div>
        <div class="mx-auto mt-3 border-b border-[#cfd8e3]" aria-hidden="true"></div>
    @endif

    <div class="mx-auto mt-5 flex max-w-5xl items-start gap-3 border border-[#f0b46a] bg-[#fdebd0] p-4 text-[14px] text-[#b4530b]">
        <span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-[#f08a24] text-sm font-bold text-white" aria-hidden="true">!</span>
        <div>Fill this in <strong>once</strong>: every scholarship {{ $ctx->isAgent() ? 'this student applies' : 'you apply' }} for uses these answers. Fields marked <span class="font-bold text-[#d9534f]">*</span> are needed before you can submit. Please click the <strong>Save</strong> button at the bottom before you leave or go to the next step. {{ $percent }}% of the required answers are filled in.@if ($ctx->isAgent()) The answers belong to the student, who can also sign in and see them.@endif</div>
    </div>

    <div class="mx-auto mt-5 grid max-w-6xl gap-5 lg:grid-cols-[190px_1fr]">
        <nav aria-label="Form sections" class="lg:sticky lg:top-4 lg:self-start">
            <ul class="border border-[#ddd] bg-[#f7f7f7] text-[13px]">
                @foreach ($sections as $key => $section)
                    @php($p = $progress[$key])
                    <li class="border-b border-[#e5e5e5] last:border-0"><a href="#{{ $key }}" class="flex items-center justify-between gap-2 px-3 py-2.5 hover:bg-white"><span><span class="text-[#999]" aria-hidden="true">› </span>{{ $section['title'] }}</span>@if ($p['required'] && $p['answered'] >= $p['required'])<span class="text-[#2c5f9e]" aria-label="done">✓</span>@elseif ($p['required'])<span class="text-xs text-[#d9534f]" aria-label="{{ $p['required'] - $p['answered'] }} to answer">{{ $p['required'] - $p['answered'] }}</span>@endif</a></li>
                @endforeach
                <li><a href="#declaration" class="flex items-center justify-between gap-2 px-3 py-2.5 hover:bg-white"><span><span class="text-[#999]" aria-hidden="true">› </span>Declaration</span>@if ($form->value('_declared_at'))<span class="text-[#2c5f9e]" aria-label="done">✓</span>@endif</a></li>
            </ul>
        </nav>

        <form method="POST" action="{{ $plan ? $ctx->route('applications.form.update', $plan) : $ctx->route('form.update') }}" novalidate>
            @csrf @method('PUT')
            <fieldset @disabled($locked) class="space-y-4 border-0 p-0">
                <legend class="sr-only">Application form</legend>
                @foreach ($sections as $key => $section)
                    <section id="{{ $key }}" class="border border-[#ddd] bg-white" aria-labelledby="{{ $key }}-h">
                        <h2 id="{{ $key }}-h" class="border-b border-[#ddd] bg-[#ececec] px-3 py-2 text-[15px] font-bold text-[#222]">{{ $section['title'] }}</h2>
                        <div class="p-4">
                            @if (isset($section['repeat']))
                                @php($rows = $form->value($key, []))
                                @for ($i = 0; $i < $section['repeat']; $i++)
                                    <div class="{{ $i > 0 ? 'mt-4 border-t border-dashed border-[#ddd] pt-4' : '' }}">
                                        <p class="mb-2 text-[13px] font-semibold text-[#666]">Entry {{ $i + 1 }}</p>
                                        <div class="grid gap-x-8 gap-y-3 lg:grid-cols-2">
                                            @foreach ($section['fields'] as $name => $def)
                                                @include('student._form-field', ['id' => "$key-$i-$name", 'field' => "sections[$key][rows][$i][$name]", 'def' => $def, 'value' => old("sections.$key.rows.$i.$name", $rows[$i][$name] ?? null), 'errorKey' => "sections.$key.rows.$i.$name", 'countries' => $countries])
                                            @endforeach
                                        </div>
                                    </div>
                                @endfor
                            @else
                                <div class="grid gap-x-8 gap-y-3 lg:grid-cols-2">
                                    @foreach ($section['fields'] as $name => $def)
                                        @include('student._form-field', ['id' => "$key-$name", 'field' => "sections[$key][$name]", 'def' => $def, 'value' => old("sections.$key.$name", $form->value($name)), 'errorKey' => "sections.$key.$name", 'countries' => $countries])
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </section>
                @endforeach

                <section id="declaration" class="border border-[#ddd] bg-white" aria-labelledby="decl-h">
                    <h2 id="decl-h" class="border-b border-[#ddd] bg-[#ececec] px-3 py-2 text-[15px] font-bold text-[#222]">Declaration</h2>
                    <div class="p-4 text-[14px]">
                        <label class="flex items-start gap-2"><input type="checkbox" name="declared" value="1" class="mt-1" @checked(old('declared', (bool) $form->value('_declared_at')))> <span><span class="text-[#d9534f]" aria-hidden="true">*</span> I hereby affirm that: {{ config('application_form.declaration') }}</span></label>
                        @if ($form->value('_declared_at'))<p class="mt-2 text-[13px] text-[#777]">Recorded on {{ \Illuminate\Support\Carbon::parse($form->value('_declared_at'))->format('j M Y') }}.</p>@endif
                    </div>
                </section>
            </fieldset>

            <div class="mt-6 flex flex-wrap items-center justify-center gap-3 text-[15px]">
                @if ($plan)<a class="border border-[#bbb] bg-white px-8 py-2.5 hover:bg-[#f5f5f5]" href="{{ $ctx->route('applications.show', $plan) }}">Previous</a>@endif
                @unless ($locked)<button class="bg-[#0b4a8f] px-10 py-2.5 font-semibold text-white hover:bg-[#093d75]">Save</button>@endunless
                @if ($plan)<a class="bg-[#2c5f9e] px-8 py-2.5 text-white hover:bg-[#244f85]" href="{{ $ctx->route('applications.materials', $plan) }}">Next: Upload Materials</a>@endif
            </div>
        </form>
    </div>
</x-dynamic-component>
