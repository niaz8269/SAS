<?php

namespace App\Http\Controllers;

use App\Domain\Seo\CscGuide;

/** The Chinese Government Scholarship guide, written from the published calls (see App\Domain\Seo\CscGuide). */
class CscGuideController extends Controller
{
    public function __invoke(CscGuide $guide)
    {
        $page = $guide->content();
        abort_if($page === null, 404); // fewer than the minimum number of calls: no page

        return view('public.guides.csc', ['page' => $page]);
    }
}
