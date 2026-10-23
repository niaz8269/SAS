<p class="text-sm text-muted">Last updated: 23 October 2026. This policy explains our fees and how cancellation and refunds work for paid services from {{ config('sas.brand.short') }}. Browsing the site, using the workspace and sending an enquiry are free.</p>

<h2>1. Our fees at a glance</h2>
@include('public.legal.fees')
<p>All amounts are in US dollars. We tell you the amount in writing before you are charged, and you are only charged after you have agreed to it.</p>
@include('public.legal.payment-options')

<h2>2. The application fee</h2>
<ul>
    <li>The application fee is {{ config('sas.fees.currency') }}{{ config('sas.fees.application') }} for each scholarship application we prepare and support, whatever type of scholarship it is.</li>
    <li>If a scholarship application is <strong>rejected</strong>, {{ config('sas.fees.currency') }}{{ config('sas.fees.application_refundable') }} of that fee is refunded to you. The remaining {{ config('sas.fees.currency') }}{{ config('sas.fees.application') - config('sas.fees.application_refundable') }} is not refundable because it covers the work already done.</li>
    <li>"Rejected" means the funder or university decides, in writing or through its official channel, not to award you the scholarship or not to admit you for that application.</li>
    <li>If you cancel <strong>before we have started work</strong> on an application, we refund the full application fee. If you withdraw after work has started, the application fee is not refunded, except as described in section 6.</li>
</ul>

<h2>3. The agency service charge</h2>
<ul>
    <li>If you are <strong>accepted</strong>, you pay our agency service charge of {{ config('sas.fees.currency') }}{{ config('sas.fees.agency_service') }}. If you are not accepted, you do not pay it.</li>
    <li>"Accepted" means you receive an official offer, in writing or through the official channel, of the scholarship (or of admission with the scholarship) for an application we supported.</li>
    <li>We send you an invoice after your acceptance is confirmed, with the due date and how to pay.</li>
</ul>

<h2>4. What is not refundable</h2>
<ul>
    <li><strong>Outcomes.</strong> We do not guarantee scholarship selection, admission, attestation acceptance or a visa decision. A rejection is handled as set out in section 2; it is not a reason for any other refund.</li>
    <li><strong>The non-refundable part of the application fee</strong> and the agency service charge once it is due.</li>
    <li><strong>Third-party costs</strong> once paid or committed to someone else (for example funder or university application fees, tests, translation, courier, attestation or visa fees). We pass on any refund the third party gives us.</li>
</ul>

<h2>5. Consultations and other services</h2>
<p>Other services (for example a paid consultation or document service) are confirmed in a written scope with the fee before you pay. If you cancel or ask to reschedule a booked consultation at least 24 hours before it, any fee is refunded or moved to a new time; within 24 hours, or if you do not attend, it is not refunded unless we cancel or are at fault.</p>

<h2>6. When we will refund in full or in part</h2>
<ul>
    <li>We cancel the service or cannot deliver it, for reasons not caused by you.</li>
    <li>We fail to deliver a core part of the agreed work and do not fix it within a reasonable time after you tell us.</li>
    <li>You were charged by mistake or twice.</li>
</ul>

<h2>7. How to ask for a refund</h2>
<p>Email <a href="mailto:{{ config('sas.enquiry_email') }}">{{ config('sas.enquiry_email') }}</a> with your name, the scholarship and your case reference. For a rejection refund, attach or forward the rejection notice. We reply within 5 working days, and approved refunds are paid back to the original payment method within 14 days. Bank or payment-provider processing times may add to this.</p>

<h2>8. Your legal rights</h2>
<p>This policy does not limit any rights you have by law as a consumer where you live, including any cooling-off or cancellation right that applies to your purchase.</p>

<h2>9. Questions or complaints</h2>
<p>Write to <a href="mailto:{{ config('sas.contact_email') }}">{{ config('sas.contact_email') }}</a> or use the <a href="{{ route('contact') }}">contact page</a>. We will look into it and answer in writing.</p>