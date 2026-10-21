<x-mail::message>
# New enquiry {{ $enquiry->reference }}

Category: {{ $enquiry->category }}
Contact preference: {{ $enquiry->contact_preference }}

Open the staff admin to read and assign it.

<x-mail::button :url="url('/admin')">Open admin</x-mail::button>
</x-mail::message>
