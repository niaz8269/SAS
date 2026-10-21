<?php

namespace App\Http\Controllers;

use App\Enums\EnquiryStatus;
use App\Mail\EnquiryReceived;
use App\Mail\StaffEnquiryAlert;
use App\Models\AnalyticsEvent;
use App\Models\Enquiry;
use App\Models\EnquiryMessage;
use App\Models\ScholarshipCycle;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** PUB-06 / COM-01. Persist first; mail is queued afterwards so an outage can never lose an enquiry. */
class EnquiryController extends Controller
{
    public function create(Request $request)
    {
        return view('public.contact', [
            'services' => Service::published()->get(),
            'category' => $request->query('category', 'general'),
            'cycle' => $request->filled('cycle') ? ScholarshipCycle::visible()->with('programme')->find($request->query('cycle')) : null,
            'serviceSlug' => $request->query('service'),
            'ts' => Crypt::encryptString((string) now()->timestamp),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'category' => ['required', Rule::in(array_keys(config('sas.enquiry_categories')))],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^\+?[0-9 ()\-]{6,32}$/', Rule::requiredIf($request->input('contact_preference') === 'phone')],
            'message' => ['required', 'string', 'min:10', 'max:4000'],
            'contact_preference' => ['required', Rule::in(['email', 'phone'])],
            'cycle_id' => ['nullable', 'integer', Rule::exists('scholarship_cycles', 'id')],
            'service' => ['nullable', 'string', 'max:96'],
            'consent' => ['accepted'],
            'website' => ['nullable', 'max:0'], // honeypot
            '_ts' => ['nullable', 'string'],
        ], [
            'consent.accepted' => 'Please confirm we may contact you about this enquiry.',
            'phone.required' => 'Add a phone number if you prefer a phone call.',
        ]);

        // Anti-spam fallback: a non-empty honeypot or an implausibly fast submit is dropped quietly.
        if (filled($request->input('website')) || $this->tooFast($request->input('_ts'))) {
            return redirect()->route('contact')->with('status', 'Thanks. If you do not hear from us, please email '.config('sas.contact_email').'.');
        }

        $token = Str::random(40);
        $duplicate = Enquiry::where('email', $data['email'])->where('category', $data['category'])->where('created_at', '>', now()->subDays(7))->first();

        $enquiry = DB::transaction(function () use ($data, $request, $token, $duplicate) {
            $enquiry = new Enquiry([
                'category' => $data['category'], 'name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'] ?? null,
                'message' => $data['message'], 'contact_preference' => $data['contact_preference'],
                'cycle_id' => $data['cycle_id'] ?? null,
                'service_id' => ! empty($data['service']) ? Service::where('slug', $data['service'])->value('id') : null,
                'consent_text' => 'I agree that SAS may contact me about this enquiry by '.$data['contact_preference'].'.',
                'consent_at' => now(),
                'view_token_hash' => hash('sha256', $token), 'view_token_expires_at' => now()->addDays(30),
            ]);
            $enquiry->user_id = $request->user()?->id;
            $enquiry->status = EnquiryStatus::New;
            $enquiry->duplicate_of_id = $duplicate?->id; // flagged, never silently merged (ORD-01)
            $enquiry->save();

            return $enquiry;
        });

        AnalyticsEvent::track('enquiry_submitted', $enquiry->cycle_id, ['category' => $enquiry->category, 'service' => $enquiry->service_id !== null]);

        // Queued after the enquiry is stored. A mail failure is retried by the queue and never rolls the enquiry back.
        $link = route('enquiries.show', ['enquiry' => $enquiry->public_id, 'token' => $token]);
        Mail::to($enquiry->email)->queue(new EnquiryReceived($enquiry, $link));
        if ($staff = config('sas.staff_notification_email')) {
            Mail::to($staff)->queue(new StaffEnquiryAlert($enquiry));
        }

        return redirect()->route('enquiries.thanks')->with('enquiry', ['reference' => $enquiry->reference, 'link' => $link]);
    }

    public function thanks(Request $request)
    {
        abort_unless($request->session()->has('enquiry'), 404);

        return view('public.enquiry-thanks', ['enquiry' => $request->session()->get('enquiry')]);
    }

    /** Owner (signed in) or limited-purpose, expiring token link. Internal notes are never shown. */
    public function show(Request $request, Enquiry $enquiry)
    {
        $this->authorizeView($request, $enquiry);

        return view('public.enquiry-show', [
            'enquiry' => $enquiry->load(['publicMessages.author', 'service']), 'token' => $request->query('token'),
        ]);
    }

    public function reply(Request $request, Enquiry $enquiry)
    {
        $this->authorizeView($request, $enquiry);
        $data = $request->validate(['body' => ['required', 'string', 'max:4000']]);
        abort_if($enquiry->status === EnquiryStatus::Closed, 409, 'This enquiry is closed.');

        EnquiryMessage::create(['enquiry_id' => $enquiry->id, 'author_id' => $request->user()?->id, 'visibility' => 'public', 'body' => $data['body']]);

        return back()->with('status', 'Your message was added.');
    }

    private function authorizeView(Request $request, Enquiry $enquiry): void
    {
        $user = $request->user();
        if ($user && $user->isActive() && $enquiry->user_id === $user->id) {
            return;
        }
        $token = (string) $request->input('token', $request->query('token'));
        $valid = $token !== '' && $enquiry->view_token_hash && hash_equals($enquiry->view_token_hash, hash('sha256', $token))
            && $enquiry->view_token_expires_at?->isFuture();
        abort_unless($valid, 404); // 404, not 403: do not reveal that the enquiry exists
    }

    private function tooFast(?string $ts): bool
    {
        if (! $ts) {
            return false; // clients without the token (e.g. assistive tooling) are not blocked
        }
        try {
            return now()->timestamp - (int) Crypt::decryptString($ts) < 2;
        } catch (\Throwable) {
            return false;
        }
    }
}
