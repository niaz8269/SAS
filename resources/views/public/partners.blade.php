<x-layouts.public title="For recruiting agents" description="Recruiting agents: manage your students' scholarship applications with us.">
    <div class="container-page py-10">
        <h1 class="text-3xl font-extrabold sm:text-4xl">For recruiting agents</h1>
        <p class="mt-3 text-muted">If you recruit students for scholarships and study abroad, we give your team an agent portal: add your students one by one or from a file, fill in their applications with them, upload their documents and follow what our reviewers say about each application.</p>
        <div class="card mt-6 p-6">
            <h2 class="text-lg font-bold">How to get an agent account</h2>
            <p class="mt-2 text-sm">Agent accounts are created by our team. Tell us about your organisation and we will set you up.</p>
            <p class="mt-2 text-sm text-muted">Each student you add is emailed an invitation to choose their own password. Agents see only their own students.</p>
            <a class="btn-primary mt-4" href="{{ route('contact', ['category' => 'general']) }}">Contact us</a>
        </div>
        <p class="mt-6 text-sm">Already have an agent account? <a class="text-navy-700 underline" href="{{ route('login') }}">Log in</a> or <a class="text-navy-700 underline" href="{{ route('partner.home') }}">go to the agent portal</a>.</p>
    </div>
</x-layouts.public>
