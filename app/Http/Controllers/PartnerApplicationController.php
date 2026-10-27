<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\PartnerApplication;
use App\Models\User;
use App\Notifications\PartnerApplicationAlert;
use App\Notifications\PartnerApplicationReceived;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;

/** The public "Be a Partner" form. It only records an application; an administrator decides (see PartnerApplications). */
class PartnerApplicationController extends Controller
{
    public function create()
    {
        return view('public.partners.apply', ['countries' => Country::orderBy('name')->pluck('name')]);
    }

    public function store(Request $request)
    {
        // Only applications that were really sent count towards the limit (3 an hour, 6 a day from one address).
        $hour = 'partner-hour:'.$request->ip();
        $day = 'partner-day:'.$request->ip();
        if (RateLimiter::tooManyAttempts($hour, 3) || RateLimiter::tooManyAttempts($day, 6)) {
            abort(429, 'You have sent several applications already. Please try again later.');
        }

        // A hidden field only robots fill in: they get the same thank-you page and nothing is saved.
        if (filled($request->input('company_site'))) {
            RateLimiter::hit($hour, 3600);
            RateLimiter::hit($day, 86400);

            return redirect()->route('partners.thanks')->with('partner_sent', true);
        }

        $data = $request->validate([
            'agency_name' => ['required', 'string', 'max:200'],
            'agency_country' => ['required', 'string', 'max:100', Rule::exists('countries', 'name')],
            'city' => ['nullable', 'string', 'max:120'],
            'website' => ['nullable', 'url:http,https', 'max:300'],
            'agency_email' => ['nullable', 'email:rfc', 'max:190'],
            'agency_phone' => ['required', 'string', 'max:40'],
            'registration_number' => ['nullable', 'string', 'max:120'],
            'years_in_business' => ['nullable', Rule::in(array_keys(PartnerApplication::YEARS))],
            'students_per_year' => ['nullable', Rule::in(array_keys(PartnerApplication::STUDENTS))],
            'markets' => ['nullable', 'string', 'max:300'],
            'about' => ['nullable', 'string', 'max:2000'],
            'applicant_name' => ['required', 'string', 'max:120'],
            'applicant_position' => ['required', 'string', 'max:120'],
            'applicant_email' => ['required', 'email:rfc', 'max:190'],
            'applicant_phone' => ['required', 'string', 'max:40'],
            'how_heard' => ['nullable', 'string', 'max:190'],
            'consent' => ['accepted'],
        ], [
            'agency_country.exists' => 'Please choose the country from the list.',
            'website.url' => 'Please write the full web address, starting with https://',
            'consent.accepted' => 'Please tick the box to confirm the information is true and that we may use it to review your application.',
        ], [
            'agency_name' => 'agency name', 'agency_country' => 'country', 'agency_phone' => 'agency phone', 'agency_email' => 'agency email',
            'applicant_name' => 'full name', 'applicant_position' => 'position', 'applicant_email' => 'email', 'applicant_phone' => 'phone',
            'years_in_business' => 'years in business', 'students_per_year' => 'number of students', 'registration_number' => 'registration number',
        ]);

        $email = mb_strtolower(trim($data['applicant_email']));
        if (User::whereRaw('lower(email) = ?', [$email])->exists()) {
            return back()->withInput()->withErrors(['applicant_email' => 'This email address already has an account. Please log in, or use a different email address for the partner account.']);
        }
        // The same person applying twice (a double click, or hoping for a quicker answer) is saved once.
        if (PartnerApplication::pending()->whereRaw('lower(applicant_email) = ?', [$email])->exists()) {
            return redirect()->route('partners.thanks')->with('partner_sent', true);
        }

        $application = new PartnerApplication([
            'agency_name' => trim($data['agency_name']), 'agency_country' => $data['agency_country'], 'city' => $data['city'] ?? null,
            'website' => $data['website'] ?? null, 'agency_email' => filled($data['agency_email'] ?? null) ? mb_strtolower(trim($data['agency_email'])) : null,
            'agency_phone' => trim($data['agency_phone']), 'registration_number' => $data['registration_number'] ?? null,
            'years_in_business' => $data['years_in_business'] ?? null, 'students_per_year' => $data['students_per_year'] ?? null,
            'markets' => $data['markets'] ?? null, 'about' => $data['about'] ?? null,
            'applicant_name' => trim($data['applicant_name']), 'applicant_position' => trim($data['applicant_position']), 'applicant_email' => $email,
            'applicant_phone' => trim($data['applicant_phone']), 'how_heard' => $data['how_heard'] ?? null,
            'consent_given' => true, 'submitted_at' => now(),
        ]);
        $application->save();
        RateLimiter::hit($hour, 3600);
        RateLimiter::hit($day, 86400);

        // The application is saved: a mail service that refuses us must not turn this into an error page.
        foreach ([[$application->applicant_email, new PartnerApplicationReceived($application)], [config('sas.staff_notification_email'), new PartnerApplicationAlert($application)]] as [$to, $notification]) {
            if (! filled($to)) {
                continue;
            }
            try {
                Notification::route('mail', $to)->notify($notification);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return redirect()->route('partners.thanks')->with('partner_sent', true);
    }

    public function thanks(Request $request)
    {
        abort_unless($request->session()->has('partner_sent'), 404);

        return view('public.partners.thanks');
    }
}
