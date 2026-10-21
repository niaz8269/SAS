<x-mail::message>
# Thank you, {{ $enquiry->name }}

We received your enquiry. Your reference is **{{ $enquiry->reference }}**.

Our team will contact you by {{ $enquiry->contact_preference }}. You can view your enquiry and add messages here (the link expires in 30 days):

<x-mail::button :url="$link">View my enquiry</x-mail::button>

We do not guarantee scholarship selection, admission or visa approval. Please never email passports or other sensitive documents.

{{ config('sas.brand.name') }}
</x-mail::message>
