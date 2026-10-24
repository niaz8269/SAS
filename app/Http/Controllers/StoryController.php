<?php

namespace App\Http\Controllers;

use App\Domain\Stories\StoryPhoto;
use App\Models\Country;
use App\Models\Testimonial;
use App\Notifications\StoryAlert;
use App\Notifications\StoryReceived;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class StoryController extends Controller
{
    public function index()
    {
        return view('public.stories.index', ['stories' => Testimonial::visible()->orderBy('sort')->orderByDesc('id')->paginate(12)]);
    }

    public function show(string $slug)
    {
        $story = Testimonial::visible()->where('slug', $slug)->whereNotNull('story')->firstOrFail();
        $more = Testimonial::visible()->where('id', '!=', $story->id)->whereNotNull('story')->whereNotNull('slug')->orderByDesc('id')->limit(3)->get();

        return view('public.stories.show', ['story' => $story, 'more' => $more]);
    }

    public function create()
    {
        return view('public.stories.create', ['countries' => Country::orderBy('name')->pluck('name')]);
    }

    public function store(Request $request)
    {
        // Only stories that were really sent count towards the limit (3 an hour, 8 a day from one address), so someone fixing a typo is never locked out.
        $hour = 'story-hour:'.$request->ip();
        $day = 'story-day:'.$request->ip();
        if (RateLimiter::tooManyAttempts($hour, 3) || RateLimiter::tooManyAttempts($day, 8)) {
            abort(429, 'You have sent several stories already. Please try again later.');
        }

        // A hidden field only robots fill in: they get the same thank-you page and nothing is saved.
        if (filled($request->input('website'))) {
            RateLimiter::hit($hour, 3600);
            RateLimiter::hit($day, 86400);

            return redirect()->route('stories.thanks')->with('story_sent', true);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'gender' => ['required', Rule::in(array_keys(Testimonial::GENDERS))],
            'country' => ['required', 'string', 'max:100', Rule::exists('countries', 'name')],
            'university' => ['required', 'string', 'max:190'],
            'scholarship' => ['nullable', 'string', 'max:190'],
            'story' => ['required', 'string', 'min:200', 'max:3000'],
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:3072', 'dimensions:min_width=200,min_height=200,max_width=8000,max_height=8000'],
            'consent' => ['accepted'],
        ], [
            'story.min' => 'Please tell us a little more: at least 200 characters (about three sentences).',
            'photo.max' => 'The picture is too big: please use one under 3 MB.',
            'photo.dimensions' => 'The picture must be at least 200 by 200 pixels.',
            'consent.accepted' => 'Please tick the box to confirm you agree that we may publish your story.',
            'country.exists' => 'Please choose your country from the list.',
        ]);

        // The same person sending the same story twice (a double click) is saved once.
        $story = trim($data['story']);
        if (Testimonial::where('submitter_email', mb_strtolower($data['email']))->where('story', $story)->where('submitted_at', '>', now()->subDay())->exists()) {
            return redirect()->route('stories.thanks')->with('story_sent', true);
        }

        $photo = null;
        if ($request->hasFile('photo')) {
            try {
                $photo = app(StoryPhoto::class)->store($request->file('photo'));
            } catch (RuntimeException $e) {
                throw ValidationException::withMessages(['photo' => 'That picture could not be used. Please try a JPG or PNG photo, or send your story without one.']);
            }
        }

        $consent = 'I agree that '.config('sas.brand.short').' may publish my story, my name as written above, my picture (or a sketch picture if I did not add one), my country and my university on its website and social media after it has been reviewed. I understand that small mistakes may be corrected, that my email address will never be shown, and that I can ask for my story to be removed at any time by writing to '.config('sas.contact_email').'.';

        $testimonial = new Testimonial([
            'name' => trim($data['name']), 'quote' => Str::limit(preg_replace('/\s+/', ' ', $story), 280), 'story' => $story,
            'country' => $data['country'], 'university' => trim($data['university']), 'scholarship' => filled($data['scholarship'] ?? null) ? trim($data['scholarship']) : null,
            'gender' => $data['gender'], 'photo_path' => $photo, 'submitter_email' => mb_strtolower($data['email']), 'source' => 'submitted',
            'review_status' => 'pending', 'published' => false, 'consent_given' => true, 'consent_date' => now()->toDateString(), 'consent_text' => $consent,
            'submitted_at' => now(), 'slug' => Testimonial::newSlug($data['name']),
        ]);
        $testimonial->save();
        RateLimiter::hit($hour, 3600);
        RateLimiter::hit($day, 86400);

        Notification::route('mail', $testimonial->submitter_email)->notify(new StoryReceived($testimonial));
        if ($staff = config('sas.staff_notification_email')) {
            Notification::route('mail', $staff)->notify(new StoryAlert($testimonial));
        }

        return redirect()->route('stories.thanks')->with('story_sent', true);
    }

    public function thanks(Request $request)
    {
        abort_unless($request->session()->has('story_sent'), 404);

        return view('public.stories.thanks');
    }
}
