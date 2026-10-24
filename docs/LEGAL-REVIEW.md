# Legal texts: what to confirm before you press "Legal texts approved"

The Privacy notice (`/legal/privacy`), the Terms (`/legal/terms`) and the Cancellation and refund policy (`/legal/refunds`) were written from what the site really does. They are **not** legal advice. Read the three pages on the live site, ideally with a lawyer who knows the law where CAS is registered, and tick every line below. When you are satisfied, sign in as Super admin, open **Administration > System health** and press **Legal texts approved**. The draft notice disappears and your name and the date are recorded. If you change a text later, press **Withdraw legal approval** first.

## Facts that must be true (they are printed on the pages)
- [ ] The operating entity is **CAS - Consultancy for Abroad Study** (footer and every legal page). If the registered name is different, change it under Website > Site settings > Legal entity line.
- [ ] Contact emails, phone, WhatsApp and WeChat are correct and read by someone every working day (Site settings).
- [ ] **Fees** (refund policy): application fee US$100 per scholarship application, of which US$50 is refunded if the application is rejected, and the agency service charge of US$500 payable only if the student is accepted. Different amounts: set `SAS_FEE_APPLICATION`, `SAS_FEE_APPLICATION_REFUNDABLE`, `SAS_FEE_AGENCY_SERVICE` in `.env`; the pages and invoices follow.
- [ ] Full refund of the application fee if the student cancels before work has started; no refund after work has started except in the cases in section 6. Consultations: refund or move if cancelled 24 hours before.
- [ ] You can answer refund emails within the promised **5 working days**.
- [ ] Payment options listed on the refunds page are the ones you really accept.
- [ ] **Governing law** (terms, section 12): disputes are decided by the courts of the country where the operating entity is registered. Confirm this is what you want.
- [ ] **Limits of responsibility** (terms, section 10): "as is", no guarantee of selection, admission or a visa. A lawyer should confirm the wording is valid where you operate.

## Privacy facts
- [ ] The list of what is collected (account, profile, documents, counsellor notes, enquiries, stories, site-use counts) matches your practice.
- [ ] Documents are stored privately and scanned for malware (needs `SAS_SCANNER` and ClamAV on the server, or uploads wait for manual review).
- [ ] Retention targets: unattached uploads about 30 days, unfinished support drafts about 90 days, case documents about 90 days after the case closes. They are what the scheduled clean-up does; change the text or the code together.
- [ ] Sharing: service providers (hosting, email, tawk.to live chat), funders and universities only when the student asks, authorities where the law requires. Add any other provider you use (for example a payment provider).
- [ ] Data may be processed in China and other countries where the providers operate. Check whether data-protection law in the countries of your students (for example the UK or EU GDPR, Pakistan, India) needs more: a named representative, a data-protection officer, or a cookie banner. This is the part that most needs a lawyer.
- [ ] Students' right to see, correct, export and delete their data works: workspace > Privacy.
- [ ] Children: the service is for people of university age; under-18s are asked to use it with a parent or guardian.
- [ ] Stories: people who send a story agree to its publication; they can ask for removal by email.

## Where the words live
The texts are in `resources/views/public/legal/` (privacy, terms, refunds, fees, payment-options). They are code, so a change needs a new release; ask the developer, or tell me what to change and I will do it and rebuild the package.
