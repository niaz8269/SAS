<?php

namespace App\Http\Controllers;

use App\Models\Consultation;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** BOOK-01: creates a *Requested* record. Staff confirm a specific time; it is never shown as a confirmed booking. */
class ConsultationController extends Controller
{
    public function create()
    {
        return view('public.consultation', ['services' => Service::published()->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'service_id' => ['nullable', Rule::exists('services', 'id')->where('published', true)],
            'preferred_from' => ['required', 'date', 'after_or_equal:today'],
            'preferred_to' => ['nullable', 'date', 'after_or_equal:preferred_from'],
            'student_timezone' => ['required', Rule::in(\DateTimeZone::listIdentifiers())],
            'contact_method' => ['required', Rule::in(['email', 'phone'])],
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^\+?[0-9 ()\-]{6,32}$/', Rule::requiredIf($request->input('contact_method') === 'phone')],
            'notes' => ['nullable', 'string', 'max:2000'],
            'consent' => ['accepted'],
            'website' => ['nullable', 'max:0'],
        ], ['consent.accepted' => 'Please confirm we may contact you to arrange the consultation.', 'phone.required' => 'A phone number is needed for a phone consultation.']);

        if (filled($request->input('website'))) {
            return redirect()->route('consultations.create');
        }

        $consultation = new Consultation(collect($data)->except(['consent', 'website'])->all());
        $consultation->user_id = $request->user()?->id;
        $consultation->status = 'requested';
        $consultation->save();

        return redirect()->route('consultations.thanks')->with('consultation_reference', $consultation->reference);
    }

    public function thanks(Request $request)
    {
        abort_unless($request->session()->has('consultation_reference'), 404);

        return view('public.consultation-thanks', ['reference' => $request->session()->get('consultation_reference')]);
    }
}
