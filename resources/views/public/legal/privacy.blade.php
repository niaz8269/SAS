<p class="text-sm text-muted">Last updated: 23 October 2026. This notice explains what personal information {{ config('sas.brand.short') }} ("SAS", "we", "us") collects, why, who sees it, how long we keep it and the choices you have. {{ config('sas.legal_entity_name') }} is responsible for your information.</p>

<h2>1. What we collect</h2>
<ul>
    <li><strong>Just browsing.</strong> Scholarship, university and destination pages need no account. Our servers keep standard technical logs (IP address, browser, pages requested, time) for security, abuse prevention and fixing errors.</li>
    <li><strong>Your account.</strong> Name, email address and a password (stored only as a one-way hash).@if (config('sas.two_step.enabled')) If you turn on two-step sign-in we store the secret needed to check your authenticator codes and your recovery codes (hashed).@endif</li>
    <li><strong>Your profile (optional).</strong> Nationality, country of residence, preferred destinations, degree level and subjects of interest, prior qualification and institution, completion year, grades and scale, language test results, a budget note, intended intake year and, only if you choose, date of birth. You decide what to fill in; none of it is mandatory.</li>
    <li><strong>Documents.</strong> Files you upload to your workspace (for example transcripts, certificates, CV, statements, language certificates, recommendation letters or a passport copy). They are stored privately, checked for malware, are not placed on public storage and are visible only to you and, where your service needs it, the staff member handling your case.</li>
    <li><strong>Your SAS counsellor.</strong> If a SAS counsellor is assigned to you, they can read your details, application form, applications and uploaded documents so that they can help you, and they keep private notes about where you stand and what you want. Each time they open your file or a document it is logged. Messages between you and your counsellor are stored in your workspace.</li>
    <li><strong>Your plans and saved items.</strong> Scholarships you save, application plans, tasks, notes and the status you set.</li>
    <li><strong>Enquiries and consultations.</strong> What you send through the contact and consultation forms (name, email, phone if you give it, message, how you prefer to be contacted) and our replies. If you message us on WhatsApp or WeChat, that platform also processes the conversation under its own terms.</li>
    <li><strong>Stories you send us.</strong> If you use &ldquo;Share your story&rdquo;, we keep what you send (name, email, gender, country, university, scholarship, your story, an optional photo, and the consent wording you agreed to). Nothing is public until our team has read and approved it. If we publish it, your name, country, university, story and photo (or a sketch picture) appear on our website and may be shared on our social media. Your email address and gender are never shown. We re-save a photo as a plain square picture, which removes hidden camera and location data. You can ask us to remove your story at any time by writing to {{ config('sas.contact_email') }}; we delete it, and the photo, from the site and from our records unless the law requires us to keep something.</li>
    <li><strong>Consent and preferences.</strong> What you agreed to, the wording shown, the time and the channel, and your notification settings.</li>
    <li><strong>Site use.</strong> Simple first-party counts such as searches, saved scholarships and enquiries sent, used to see what is useful. We do not use advertising trackers or sell data.</li>
</ul>

<h2>2. Why we use it and our legal basis</h2>
<ul>
    <li>To run your workspace, answer your enquiries and deliver services you ask for (performance of a contract or steps you request before one).</li>
    <li>To send deadline reminders and service messages you have chosen to receive, and marketing only if you switch it on (your consent, which you can withdraw at any time).</li>
    <li>To keep the site secure, prevent abuse and fraud, keep accurate records and meet legal duties (legitimate interests and legal obligation).</li>
    <li>To improve the site using aggregate counts (legitimate interests).</li>
</ul>

<h2>3. Cookies</h2>
<p>We use only the cookies the site needs to work: a session cookie, a security (CSRF) token, and, if you tick "remember me", a sign-in cookie. We do not set advertising or cross-site tracking cookies of our own.</p>
@if (config('sas.tawk_embed'))
<p><strong>Live chat.</strong> The public pages of this site (not your signed-in workspace) load the chat widget of tawk.to. When the widget loads, tawk.to sets its own cookies or similar storage in your browser to run the chat and recognise a returning visitor, and it can see your IP address, browser and the page you are on. Anything you type into the chat is processed by tawk.to under <a href="https://www.tawk.to/privacy-policy/" rel="noopener" target="_blank">its own privacy policy</a>. Do not send passport numbers or other sensitive documents in the chat; use your workspace instead.</p>
@endif

<h2>4. Who we share it with</h2>
<p>We do not sell your information. We share it only with:</p>
<ul>
    <li><strong>Service providers</strong> that host the site and files and deliver email on our behalf, who may only use it for that purpose.@if (config('sas.tawk_embed')) The live chat on the public pages is run by tawk.to.@endif</li>
    <li><strong>Funders, universities or authorities</strong>, but only when you ask us to help with an application and only the information that application needs.</li>
    <li><strong>Authorities or advisers</strong> where the law requires it or to protect rights and safety.</li>
</ul>
<p>Your information may be processed in countries other than where you live, including China and countries where our providers operate. We use contracts and security measures intended to protect it wherever it is handled.</p>

<h2>5. How long we keep it</h2>
<ul>
    <li>Your account and workspace stay until you delete them.</li>
    <li>Uploads that you never attach to anything are removed after about 30 days.</li>
    <li>Unfinished support drafts are removed after about 90 days.</li>
    <li>Documents attached to a service case are removed about 90 days after the case closes, unless you ask us to keep them or the law requires longer.</li>
    <li>Enquiries, consent records and basic accounting records are kept as long as needed to respond, show what you agreed to and meet legal duties.</li>
</ul>
<p>These periods are our current targets and may be adjusted; the date at the top shows the latest version.</p>

<h2>6. Your choices and rights</h2>
<ul>
    <li><strong>Access and export.</strong> Download a copy of your data from your workspace under Privacy.</li>
    <li><strong>Correct</strong> your profile at any time; ask us to fix anything else.</li>
    <li><strong>Delete.</strong> Request deletion of your account from your workspace; we send a confirmation link to your email, and deleting removes your profile, plans and uploaded documents (we may keep limited records we must keep by law).</li>
    <li><strong>Notifications.</strong> Change or switch off reminders and marketing under Notifications, or use the unsubscribe link in an email.</li>
    <li><strong>Object or restrict</strong> certain uses, and <strong>withdraw consent</strong> at any time; this does not affect what was done before.</li>
    <li><strong>Complain</strong> to the data protection authority where you live if you think we have handled your information wrongly. Please contact us first so we can put it right.</li>
</ul>

<h2>7. Security</h2>
<p>We use encrypted connections, hashed passwords, @if (config('sas.two_step.enabled'))optional two-step sign-in (required for staff), @endif private file storage with malware scanning, role-based access for staff and logs of who accessed what. No system is perfectly secure; if a breach affects you we will tell you and the authorities as the law requires.</p>

<h2>8. Children</h2>
<p>The service is for students of university-application age. If you are under 18, please use it with a parent or guardian. If you believe a child gave us information without permission, contact us and we will delete it.</p>

<h2>9. Changes</h2>
<p>We will update this notice when our practices change and show the new date at the top. For significant changes we will tell account holders.</p>

<h2>10. Contact</h2>
<p>For any privacy question or request: <a href="mailto:{{ config('sas.contact_email') }}">{{ config('sas.contact_email') }}</a>, or write through the <a href="{{ route('contact') }}">contact page</a>.</p>