<x-layouts.public title="Image credits" description="Where the photographs on this site come from.">
    <div class="container-page py-10">
        <h1 class="text-3xl font-extrabold sm:text-4xl">Image credits</h1>
        <p class="mt-3 text-muted">Photographs on this site are used under the free Unsplash licence, under the open licences shown below (Wikimedia Commons), or with the owner's permission. Thank you to the photographers.</p>
        <ul class="mt-6 space-y-3">
            @foreach ($bundled as $file => $c)
                <li class="card flex min-w-0 items-center gap-4 p-3">
                    <img src="{{ asset($file) }}" alt="" class="h-16 w-24 shrink-0 rounded object-cover" width="96" height="64" loading="lazy">
                    <span class="min-w-0 break-words text-sm [overflow-wrap:anywhere]"><strong>{{ $c['subject'] }}</strong><br>Photo: <a class="text-navy-700 underline" rel="noopener" href="{{ $c['url'] }}">{{ $c['name'] }}</a> on Unsplash</span>
                </li>
            @endforeach
            @foreach ($library as $m)
                <li class="card flex min-w-0 items-center gap-4 p-3">
                    @if ($m->isImage())<img src="{{ $m->url() }}" alt="" class="h-16 w-24 shrink-0 rounded object-cover" width="96" height="64" loading="lazy">@endif
                    <span class="min-w-0 break-words text-sm [overflow-wrap:anywhere]"><strong>{{ $m->name }}</strong><br>{{ $m->credit }}</span>
                </li>
            @endforeach
        </ul>
        @if (! empty($cityPhotos))
            <h2 class="mt-10 text-2xl font-bold">City photographs</h2>
            <p class="mt-2 text-sm text-muted">Skyline photographs from Wikimedia Commons, shown on universities that have no campus photo of their own.</p>
            <ul class="mt-4 grid gap-3 sm:grid-cols-2">
                @foreach ($cityPhotos as $slug => $c)
                    <li class="card flex min-w-0 items-center gap-3 p-3">
                        <img src="{{ asset('images/cities/'.$slug.'.jpg') }}" alt="" class="h-14 w-24 shrink-0 rounded object-cover" width="96" height="54" loading="lazy">
                        <span class="min-w-0 break-words text-sm [overflow-wrap:anywhere]"><strong>{{ $c['subject'] }}</strong><br><a class="text-navy-700 underline" rel="noopener" href="{{ $c['url'] }}">{{ $c['artist'] }}</a>, {{ $c['license'] }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
        @if (! empty($universityPhotos))
            <h2 class="mt-10 text-2xl font-bold">University photographs</h2>
            <p class="mt-2 text-sm text-muted">Campus photographs from Wikimedia Commons, cropped to 16:9. Each links to its file page with the author and licence.</p>
            <ul class="mt-4 grid gap-3 sm:grid-cols-2">
                @foreach ($universityPhotos as $slug => $c)
                    <li class="card flex min-w-0 items-center gap-3 p-3">
                        <img src="{{ asset('images/universities/'.$slug.'.jpg') }}" alt="" class="h-14 w-24 shrink-0 rounded object-cover" width="96" height="54" loading="lazy">
                        <span class="min-w-0 break-words text-sm [overflow-wrap:anywhere]"><strong>{{ $c['subject'] }}</strong><br><a class="text-navy-700 underline" rel="noopener" href="{{ $c['url'] }}">{{ $c['artist'] }}</a>, {{ $c['license'] }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-layouts.public>