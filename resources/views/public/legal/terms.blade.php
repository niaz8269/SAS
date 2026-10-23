<p class="text-sm text-muted">Last updated: 23 October 2026. These terms apply to this website, your student workspace and any service you request from {{ config('sas.brand.short') }} ("SAS", "we", "us").</p>

<h2>1. Who we are and what we do</h2>
<p>SAS is an information and support service for students looking for scholarships and study opportunities abroad. We publish scholarship information summarised from official sources, offer a workspace for planning and tracking applications, and offer advisory services (for example profile review, document checking, application support and consultations). We are <strong>not</strong> a scholarship provider, a university, an embassy or a government body, and we are not affiliated with or endorsed by any funder unless we say so in writing. {{ config('sas.legal_entity') }}.</p>

<h2>2. Information, not a guarantee</h2>
<p>Scholarship details (dates, eligibility, funding, programmes) are summarised from official sources and can change without notice. Every listing links to its official source and shows when we last checked it. Always confirm the latest details with the funder before you apply. We do not guarantee that a scholarship, programme or date shown is current, that you will be eligible, or that you will be selected, admitted, receive funding, have documents attested or be granted a visa. Those decisions belong to the funders, universities and authorities.</p>

<h2>3. Your account</h2>
<ul>
    <li>Browsing the site never needs an account. To use the workspace you must give accurate information and keep your password and authenticator app safe. You are responsible for activity under your account; tell us at once if you think it has been misused.</li>
    <li>You must be old enough to enter a contract where you live. If you are under 18, please use the service together with a parent or guardian.</li>
    <li>We may suspend or close an account that breaks these terms, is used for fraud, or puts others or the service at risk. You can ask us to delete your account at any time (see the privacy notice).</li>
</ul>

<h2>4. Applications you track here</h2>
<p>Tracking an application in your workspace does not submit it and does not create an application with any funder. You apply directly with the funder or university on their official channels. We never ask for the password to any university, embassy, funder or portal account, and you should never share one with anyone, including us.</p>

<h2>5. Services and fees</h2>
@include('public.legal.fees')
<ul>
    <li>Every service starts with a free enquiry. Before you are charged we confirm in writing what we will do, the deliverables, the fee and how to pay. Application support starts once you have agreed to that and paid the application fee.</li>
    <li>The application fee is charged per scholarship application. If the application is rejected, part of it is refunded; the agency service charge is due only if you are accepted. Exact terms, including what "rejected" and "accepted" mean, are in the <a href="{{ route('legal.show', 'refunds') }}">cancellation and refund policy</a>.</li>
    <li>We do not promise outcomes. We do not guarantee selection, admission, attestation acceptance or a visa decision, and a rejection by a third party is not a failure of the service.</li>
    <li>Fees charged by third parties (funder or university application fees, translation, courier, attestation, tests, visa fees) are separate and are paid by you unless we agree otherwise in writing.</li>
</ul>
<h2>6. What you give us</h2>
<ul>
    <li>You must have the right to give us the documents and information you upload or send, and they must be true and not misleading. Do not alter documents or submit someone else's work as your own. We may refuse or stop a service if we believe a document is false.</li>
    <li>You keep ownership of your documents and content. You allow us to store and use them only to provide the service you asked for, as set out in the <a href="{{ route('legal.show', 'privacy') }}">privacy notice</a>.</li>
    <li>Please do not send passport numbers or other sensitive details in the general enquiry form; use the secure workspace for documents.</li>
</ul>

<h2>7. Acceptable use</h2>
<p>Do not misuse the site: no attempts to break, overload or scrape it in bulk, no unlawful, abusive or deceptive content, no impersonation, and no attempt to access another person's data. We may block access that threatens the service or other users.</p>

<h2>8. Our content and links</h2>
<p>The site design, text we write, logos and software belong to us or our licensors; photographs are credited on the <a href="{{ route('credits') }}">image credits</a> page. You may link to us and quote short extracts with a credit, but please do not copy the site or its data in bulk. Links to official and third-party websites are for your convenience; we do not control them and are not responsible for their content.</p>

<h2>9. Accuracy and corrections</h2>
<p>If you spot something wrong or out of date, tell us through the <a href="{{ route('corrections.create') }}">corrections form</a> and we will check it against the official source.</p>

<h2>10. Limits of our responsibility</h2>
<p>We take care to keep information accurate, but the site and its information are provided "as is". To the extent the law allows, we are not liable for losses that arise from relying on information that later changed, from decisions of funders, universities or authorities, from missed deadlines, or from events outside our control. Nothing in these terms limits liability that cannot be limited by law, including for fraud, or limits any rights you have as a consumer.</p>

<h2>11. Changes to the site and these terms</h2>
<p>We may change the site, a service or these terms. The date at the top shows when they were last updated. If a change is significant we will tell account holders by email or in the workspace. Using the site after a change means you accept it.</p>

<h2>12. Governing law and disputes</h2>
<p>These terms are governed by the law of the country where the operating entity named in the footer is registered, and the courts of that country decide any dispute, without removing any mandatory rights you have where you live. Please contact us first at <a href="mailto:{{ config('sas.contact_email') }}">{{ config('sas.contact_email') }}</a>; most problems can be solved quickly.</p>

<h2>13. Contact</h2>
<p>General: <a href="mailto:{{ config('sas.contact_email') }}">{{ config('sas.contact_email') }}</a>. Enquiries: <a href="mailto:{{ config('sas.enquiry_email') }}">{{ config('sas.enquiry_email') }}</a>. Admissions: <a href="mailto:{{ config('sas.admissions_email') }}">{{ config('sas.admissions_email') }}</a>. Phone and WhatsApp: {{ config('sas.contact_phone') }}. WeChat: {{ config('sas.contact_wechat') }}.</p>