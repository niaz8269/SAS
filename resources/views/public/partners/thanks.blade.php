<x-layouts.public title="Application received" description="Thank you for applying to become a partner." :noindex="true">
    <section class="container-page py-12">
        <div class="card mx-auto max-w-2xl p-8 text-center" role="status">
            <h1 class="text-3xl font-extrabold">Thank you for applying</h1>
            <p class="mt-3 text-muted">We have your application. Our team will review it. If it is approved, we email you a link to choose your own password and open your partner account. If we need anything more, we will write to the email address you gave.</p>
            <div class="mt-6 flex flex-wrap justify-center gap-3"><a class="btn-primary" href="{{ route('home') }}">Back to the home page</a></div>
        </div>
    </section>
</x-layouts.public>
