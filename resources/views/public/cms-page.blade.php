<x-layouts.public :title="$page->title" :description="$page->meta_description">
    <div class="container-page max-w-3xl py-10">
        <h1 class="text-3xl font-extrabold sm:text-4xl">{{ $page->title }}</h1>
        <div class="prose-sas mt-6">{!! \App\Support\Html::clean($page->body) !!}</div>
    </div>
</x-layouts.public>
