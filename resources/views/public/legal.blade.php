@php
    $titles = ['privacy' => 'Privacy notice', 'terms' => 'Terms of use', 'refunds' => 'Service cancellation and refund policy', 'accessibility' => 'Accessibility statement'];
@endphp
<x-layouts.public :title="$titles[$page]">
    <div class="container-page max-w-3xl py-10">
        <h1 class="text-3xl font-extrabold">{{ $titles[$page] }}</h1>
        <div class="mt-4 rounded-md border border-warn/30 bg-warn-soft px-4 py-3 text-sm text-warn" role="note">
            <strong>Draft for owner review.</strong> This text is a working draft and has not been approved by the operating entity or legal counsel. It must be reviewed before launch.
        </div>
        <div class="prose-sas mt-6">
            @switch($page)
                @case('privacy')
                    <h2>What we collect</h2>
                    <p>Browsing scholarships collects no personal information beyond standard server logs. If you create an account we store your name, email and the profile details you choose to add. Documents you upload are kept privately and are never placed on public storage.</p>
                    <h2>How we use it</h2>
                    <p>To run your workspace, answer your enquiries and send reminders you have opted in to. Marketing messages are off unless you turn them on. We record the wording, time and channel of each consent.</p>
                    <h2>Your choices</h2>
                    <p>You can change notification preferences at any time, delete uploaded documents, and ask for an export or deletion of your account data. Retention periods are pending owner approval.</p>
                    @break
                @case('terms')
                    <h2>Information, not a guarantee</h2>
                    <p>Scholarship information is summarised from official sources and may change. Always confirm the latest details with us or the funder. SAS does not guarantee scholarship selection, admission, attestation acceptance or visa approval.</p>
                    <h2>Applications</h2>
                    <p>Tracking an application in your workspace does not submit it. We never ask for passwords to university, embassy or scholarship accounts.</p>
                    <h2>Services</h2>
                    <p>Services start with an enquiry. Scope, deliverables, any fee and revision limits are confirmed with you before work starts.</p>
                    @break
                @case('refunds')
                    <p>Online payments are not available yet. Fees, cancellation and refund terms will be set out here and agreed with you in writing before any paid service begins. These terms are pending owner approval.</p>
                    @break
                @case('accessibility')
                    <p>We aim to meet WCAG 2.2 level AA for core journeys: searching, reading a scholarship, enquiring and using your workspace. If you meet a barrier, email <a href="mailto:{{ config('sas.accessibility_email') }}">{{ config('sas.accessibility_email') }}</a> and we will help and fix it.</p>
                    @break
            @endswitch
        </div>
        <p class="mt-8 text-sm text-muted">{{ config('sas.legal_entity') }}</p>
    </div>
</x-layouts.public>
