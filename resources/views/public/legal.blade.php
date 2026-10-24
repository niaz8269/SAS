@php
    $titles = ['privacy' => 'Privacy notice', 'terms' => 'Terms and conditions', 'refunds' => 'Cancellation and refund policy', 'accessibility' => 'Accessibility statement'];
@endphp
<x-layouts.public :title="$titles[$page]">
    <div class="container-page py-10">
        <h1 class="text-3xl font-extrabold">{{ $titles[$page] }}</h1>
        @unless (config('sas.legal_approved'))
        <div class="mt-4 rounded-md border border-warn/30 bg-warn-soft px-4 py-3 text-sm text-warn" role="note">
            <strong>Draft for owner review.</strong> This text is a working draft and has not been approved by the operating entity or legal counsel. It must be reviewed before launch.
        </div>
        @endunless
        <div class="prose-sas mt-6">
            @switch($page)
                @case('privacy') @include('public.legal.privacy') @break
                @case('terms') @include('public.legal.terms') @break
                @case('refunds') @include('public.legal.refunds') @break
                @case('accessibility')
                    <p>We aim to meet WCAG 2.2 level AA for core journeys: searching, reading a scholarship, enquiring and using your workspace. If you meet a barrier, email <a href="mailto:{{ config('sas.accessibility_email') }}">{{ config('sas.accessibility_email') }}</a> and we will help and fix it.</p>
                    @break
            @endswitch
        </div>
        <p class="mt-8 text-sm text-muted">{{ config('sas.legal_entity') }}</p>
    </div>
</x-layouts.public>
