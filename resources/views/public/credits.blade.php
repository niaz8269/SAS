<x-layouts.public title="Image credits" description="Where the photographs on this site come from.">
    <div class="container-page max-w-3xl py-10">
        <h1 class="text-3xl font-extrabold sm:text-4xl">Image credits</h1>
        <p class="mt-3 text-muted">Photographs on this site are used under the free Unsplash licence or with the owner's permission. Thank you to the photographers.</p>
        <ul class="mt-6 space-y-3">
            @foreach ($bundled as $file => $c)
                <li class="card flex items-center gap-4 p-3">
                    <img src="{{ asset($file) }}" alt="" class="h-16 w-24 shrink-0 rounded object-cover" width="96" height="64" loading="lazy">
                    <span class="text-sm"><strong>{{ $c['subject'] }}</strong><br>Photo: <a class="text-navy-700 underline" rel="noopener" href="{{ $c['url'] }}">{{ $c['name'] }}</a> on Unsplash</span>
                </li>
            @endforeach
            @foreach ($library as $m)
                <li class="card flex items-center gap-4 p-3">
                    @if ($m->isImage())<img src="{{ $m->url() }}" alt="" class="h-16 w-24 shrink-0 rounded object-cover" width="96" height="64" loading="lazy">@endif
                    <span class="text-sm"><strong>{{ $m->name }}</strong><br>{{ $m->credit }}</span>
                </li>
            @endforeach
        </ul>
    </div>
</x-layouts.public>