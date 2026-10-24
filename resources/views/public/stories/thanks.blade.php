<x-layouts.public title="Thank you" description="Thank you for sharing your story." :noindex="true">
    <section class="container-page py-12">
        <div class="card mx-auto max-w-2xl p-8 text-center" role="status">
            <h1 class="text-3xl font-extrabold">Thank you for sharing your story</h1>
            <p class="mt-3 text-muted">We have it. Our team will read it, and we will write to you at the email you gave if we need anything. Nothing is published before it has been reviewed.</p>
            <div class="mt-6 flex flex-wrap justify-center gap-3"><a class="btn-primary" href="{{ route('stories.index') }}">Read other stories</a><a class="btn-outline" href="{{ route('home') }}">Back to the home page</a></div>
        </div>
    </section>
</x-layouts.public>
