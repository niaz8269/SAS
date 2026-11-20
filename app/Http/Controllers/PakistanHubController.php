<?php

namespace App\Http\Controllers;

use App\Domain\Seo\PakistanHub;

/** Scholarships for Pakistani students: the hub and its facets (MS, PhD, BS, fully funded, by destination). See App\Domain\Seo\PakistanHub. */
class PakistanHubController extends Controller
{
    public function __construct(private readonly PakistanHub $hub) {}

    public function hub()
    {
        return $this->show(null);
    }

    public function facet(string $facet)
    {
        return $this->show($facet);
    }

    private function show(?string $facet)
    {
        $page = $this->hub->find($facet);
        abort_if($page === null, 404); // fewer than the minimum number of scholarships: no page, no thin content

        $cycles = $this->hub->query($page)->paginate(24)->withQueryString();
        abort_if($cycles->currentPage() > max(1, $cycles->lastPage()), 404);

        $all = $this->hub->pages();

        return view('public.scholarships.pakistan', [
            'page' => $page,
            'content' => $this->hub->content($page),
            'cycles' => $cycles,
            'hubPage' => $all->firstWhere('facet', null),
            'degreePages' => $all->filter(fn ($p) => in_array($p['facet'], ['ms', 'phd', 'bs', 'fully-funded'], true))->values(),
            'destinationPages' => $all->filter(fn ($p) => str_starts_with((string) $p['facet'], 'in-'))->sortByDesc('count')->values(),
        ]);
    }
}
