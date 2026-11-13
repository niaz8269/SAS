<x-mail::message>
# New enquiry {{ $enquiry->reference }}

**Name:** {{ $enquiry->name }}
**Email:** {{ $enquiry->email }}
@if ($enquiry->phone)
**Phone / WhatsApp:** {{ $enquiry->phone }}
@endif
**Category:** {{ $enquiry->category }}
**Prefers contact by:** {{ $enquiry->contact_preference }}

**Message**

{{ $enquiry->message }}

The person agreed to be contacted about this enquiry. Reply to this email to answer them, or open the staff admin to assign it.

<x-mail::button :url="url('/admin')">Open admin</x-mail::button>
</x-mail::message>
