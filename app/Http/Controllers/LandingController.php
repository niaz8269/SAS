<?php

namespace App\Http\Controllers;

use App\Domain\Seo\Landing;

/** Search-friendly lists of scholarships (by destination, degree level or funding), made from the published data. See App\Domain\Seo\Landing. */
class LandingController extends Controller
{
    public function __construct(private readonly Landing $landing) {}

    public function destination(string $country)
    {
        return $this->show('in', $country);
    }

    public function degree(string $degree)
    {
        return $this->show('for', $degree);
    }

    public function funding(string $funding)
    {
        return $this->show('funding', $funding);
    }

    public function subject(string $subject)
    {
        return $this->show('subject', $subject);
    }

    public function from(string $country)
    {
        return $this->show('from', $country);
    }

    public function open()
    {
        return $this->show('open', 'any-nationality');
    }

    private function show(string $type, string $slug)
    {
        $page = $this->landing->find($type, $slug);
        abort_if($page === null, 404); // fewer than the minimum number of scholarships: no page, no thin content

        $cycles = $this->landing->query($page)->paginate(24)->withQueryString();
        abort_if($cycles->currentPage() > max(1, $cycles->lastPage()), 404); // a page number past the end would be an empty page that search engines could index

        return view('public.scholarships.landing', [
            'page' => $page,
            'content' => $this->landing->content($page),
            'cycles' => $cycles,
            'neighbours' => $this->landing->neighbours($page),
        ]);
    }
}
