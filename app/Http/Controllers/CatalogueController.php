<?php

namespace App\Http\Controllers;

use App\Models\University;
use App\Models\UniversityProgramme;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/** Public pages for the China university and programme catalogue. Read-only; staff edit the data in the admin. */
class CatalogueController extends Controller
{
    public function universities(Request $request)
    {
        $data = $request->validate(['q' => 'nullable|string|max:80', 'province' => 'nullable|string|max:64', 'sort' => 'nullable|in:name,programmes']);
        $q = trim((string) ($data['q'] ?? ''));

        $universities = University::query()->inCatalogue()->withCount(['programmes as programmes_count' => fn ($p) => $p->where('published', true)])
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('name', 'like', '%'.$this->like($q).'%')->orWhere('name_zh', 'like', '%'.$this->like($q).'%')))
            ->when(! empty($data['province']), fn ($query) => $query->where('province', $data['province']))
            ->when(($data['sort'] ?? 'name') === 'programmes', fn ($query) => $query->orderByDesc('programmes_count')->orderBy('name'), fn ($query) => $query->orderBy('name'))
            ->paginate(24)->withQueryString();

        return view('public.universities.index', [
            'universities' => $universities,
            'provinces' => Cache::remember('catalogue.provinces', 3600, fn () => University::inCatalogue()->whereNotNull('province')->distinct()->orderBy('province')->pluck('province')->all()),
            'filters' => ['q' => $q, 'province' => $data['province'] ?? '', 'sort' => $data['sort'] ?? 'name'],
        ]);
    }

    public function university(Request $request, University $university)
    {
        abort_unless($university->published && $university->csc_key, 404);
        $data = $request->validate(['q' => 'nullable|string|max:80', 'level' => 'nullable|in:'.implode(',', array_keys(UniversityProgramme::LEVELS)), 'language' => 'nullable|string|max:40', 'csc' => 'nullable|boolean']);

        $query = $this->programmeFilters($university->programmes()->where('published', true), $data);
        $counts = $university->programmes()->where('published', true)->selectRaw('level, count(*) as c')->groupBy('level')->pluck('c', 'level');

        return view('public.universities.show', [
            'university' => $university->load('country'),
            'programmes' => $query->orderBy('level')->orderByRaw('COALESCE(name_en, name_zh)')->paginate(40)->withQueryString(),
            'levelCounts' => $counts,
            'filters' => ['q' => trim((string) ($data['q'] ?? '')), 'level' => $data['level'] ?? '', 'language' => $data['language'] ?? '', 'csc' => (bool) ($data['csc'] ?? false)],
            'languages' => UniversityProgramme::languageLabels(),
        ]);
    }

    public function programmes(Request $request)
    {
        $data = $request->validate(['q' => 'nullable|string|max:80', 'level' => 'nullable|in:'.implode(',', array_keys(UniversityProgramme::LEVELS)), 'language' => 'nullable|string|max:40',
            'csc' => 'nullable|boolean', 'province' => 'nullable|string|max:64']);

        $query = UniversityProgramme::query()->public()->with('university:id,name,name_zh,slug,province,city');
        $query = $this->programmeFilters($query, $data);
        if (! empty($data['province'])) {
            $query->whereHas('university', fn ($u) => $u->where('province', $data['province']));
        }

        return view('public.programmes.index', [
            'programmes' => $query->orderByRaw('COALESCE(name_en, name_zh)')->orderBy('id')->paginate(30)->withQueryString(),
            'provinces' => Cache::remember('catalogue.provinces', 3600, fn () => University::inCatalogue()->whereNotNull('province')->distinct()->orderBy('province')->pluck('province')->all()),
            'languages' => UniversityProgramme::languageLabels(),
            'filters' => ['q' => trim((string) ($data['q'] ?? '')), 'level' => $data['level'] ?? '', 'language' => $data['language'] ?? '', 'csc' => (bool) ($data['csc'] ?? false), 'province' => $data['province'] ?? ''],
        ]);
    }

    /** Shared programme filters: keyword (English or Chinese name), level, teaching language, scholarship star. */
    private function programmeFilters($query, array $data)
    {
        $q = trim((string) ($data['q'] ?? ''));

        return $query
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('name_en', 'like', '%'.$this->like($q).'%')->orWhere('name_zh', 'like', '%'.$this->like($q).'%')))
            ->when(! empty($data['level']), fn ($w) => $w->where('level', $data['level']))
            ->when(! empty($data['language']), fn ($w) => $w->whereIn('teaching_language', UniversityProgramme::rawLanguagesFor($data['language']) ?: ['__none__']))
            ->when(! empty($data['csc']), fn ($w) => $w->where('csc_eligible', true));
    }

    private function like(string $s): string
    {
        return addcslashes($s, '%_\\');
    }
}
