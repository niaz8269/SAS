@php($f = config('sas.fees'))
<div class="not-prose my-4 overflow-x-auto rounded-lg border border-line bg-white">
    <table class="w-full text-left text-sm">
        <caption class="sr-only">Our fees</caption>
        <thead class="bg-ivory-deep text-muted"><tr><th scope="col" class="px-4 py-2">Fee</th><th scope="col" class="px-4 py-2">Amount</th><th scope="col" class="px-4 py-2">When it is charged</th><th scope="col" class="px-4 py-2">Refund</th></tr></thead>
        <tbody class="align-top">
            <tr class="border-t border-line"><th scope="row" class="px-4 py-3 font-semibold">Application fee</th><td class="px-4 py-3 whitespace-nowrap">{{ $f['currency'] }}{{ $f['application'] }} per application</td><td class="px-4 py-3">When we start preparing your application. The same fee applies to every type of scholarship.</td><td class="px-4 py-3">{{ $f['currency'] }}{{ $f['application_refundable'] }} is refunded if the application is rejected. The other {{ $f['currency'] }}{{ $f['application'] - $f['application_refundable'] }} is not refundable.</td></tr>
            <tr class="border-t border-line"><th scope="row" class="px-4 py-3 font-semibold">Agency service charge</th><td class="px-4 py-3 whitespace-nowrap">{{ $f['currency'] }}{{ $f['agency_service'] }}</td><td class="px-4 py-3">Only after you are accepted. Nothing more is due if you are not.</td><td class="px-4 py-3">Not charged unless you are accepted.</td></tr>
        </tbody>
    </table>
</div>