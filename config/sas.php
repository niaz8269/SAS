<?php

return [
    'brand' => [
        // The owner's spelling is intentional and must be preserved.
        'name' => 'SAS – Scholarships Application Services',
        'short' => 'SAS',
        'tagline' => 'Turning Potential into Admission',
    ],

    // Owner decisions still open (docs/DECISIONS.md). Shown in the footer until confirmed.
    'legal_entity_name' => env('SAS_LEGAL_ENTITY_NAME', 'CAS - Consultancy for Abroad Study'),
    'legal_entity' => env('SAS_LEGAL_ENTITY', 'Operated by CAS - Consultancy for Abroad Study'),
    // Set SAS_LEGAL_APPROVED=true once the operating entity or counsel has approved the privacy, terms and refund texts; it hides the draft notice on those pages.
    'legal_approved' => (bool) env('SAS_LEGAL_APPROVED', false),

    // Service fees in US dollars, shown on the Services, Terms and Refund pages. The application fee is charged per scholarship application;
    // part of it is refunded if the application is rejected; the agency service charge is due only after acceptance.
    'fees' => [
        'currency' => 'US$',
        'application' => (int) env('SAS_FEE_APPLICATION', 100),
        'application_refundable' => (int) env('SAS_FEE_APPLICATION_REFUNDABLE', 50),
        'agency_service' => (int) env('SAS_FEE_AGENCY_SERVICE', 500),
    ],

    // Contact details (editable in the admin under Website, Site settings; these are the defaults).
    'contact_email' => env('SAS_CONTACT_EMAIL', 'info@sas.cas.moe'),
    'enquiry_email' => env('SAS_ENQUIRY_EMAIL', 'enquiry@sas.cas.moe'),
    'admissions_email' => env('SAS_ADMISSIONS_EMAIL', 'admissions@sas.cas.moe'),
    'accessibility_email' => env('SAS_ACCESSIBILITY_EMAIL', 'info@sas.cas.moe'),
    'contact_phone' => env('SAS_CONTACT_PHONE', '+86 176 4041 2827'),
    // Live chat widget (tawk.to) loaded on the public pages only. Empty turns it off. Never loaded on signed-in or admin pages, where a third-party script could read personal data.
    'tawk_embed' => env('SAS_TAWK_EMBED', 'https://embed.tawk.to/6adcff3f803f96347af46f30/1k5nka51i'),
    'contact_whatsapp' => env('SAS_CONTACT_WHATSAPP', '+86 176 4041 2827'),
    'contact_wechat' => env('SAS_CONTACT_WECHAT', 'cas-scholarships'),
    // New-enquiry alerts go to the enquiry mailbox.
    'staff_notification_email' => env('SAS_STAFF_NOTIFICATION_EMAIL', 'enquiry@sas.cas.moe'),
    // Shared secret for the signed email-delivery webhook. Empty = endpoint disabled.
    'mail_webhook_secret' => env('SAS_MAIL_WEBHOOK_SECRET'),

    /*
     * Role => permissions (AUTH-05: publish, refund and role-management are separately grantable).
     * Permission names contain a dot; App\Providers\AuthServiceProvider resolves them through the Gate.
     */
    'permissions' => [
        // The owner: everything, including who holds which role.
        'super_admin' => ['*'],
        // Runs the service and the website: content (draft, review, publish), imports, articles, reference data, the website itself, enquiries,
        // services, cases, fees and payments, agents, document rules, students and counsellors, reports and users. Can also counsel students.
        'administrator' => [
            'content.draft', 'content.review', 'content.publish', 'content.override', 'import.stage', 'users.manage', 'audit.view', 'reports.view',
            'enquiries.view_all', 'enquiries.assign', 'services.manage', 'articles.manage', 'taxonomy.manage', 'corrections.manage', 'site.manage',
            'cases.manage', 'finance.view', 'invoices.manage', 'partners.manage', 'documents.manage', 'students.manage',
            'enquiries.assigned', 'consultations.assigned', 'cases.assigned', 'students.assigned',
        ],
        // Looks after assigned students, enquiries, consultations and cases.
        'advisor' => ['enquiries.assigned', 'consultations.assigned', 'cases.assigned', 'students.assigned'],
        'partner_manager' => [], // a recruiting agent's access is decided by their organisation and by policies, not by a role permission
    ],
    /*
     * How long before the Needs attention list and the admin alert email call something late. Change them in .env.
     */
    'sla' => [
        'enquiry_hours' => (int) env('SAS_SLA_ENQUIRY_HOURS', 24), // no first reply to a new enquiry
        'reply_hours' => (int) env('SAS_SLA_REPLY_HOURS', 48), // a student's message nobody answered
        'review_days' => (int) env('SAS_SLA_REVIEW_DAYS', 3), // an application waiting for SAS review
        'returned_days' => (int) env('SAS_SLA_RETURNED_DAYS', 7), // sent back and still not fixed
        'unpaid_days' => (int) env('SAS_SLA_UNPAID_DAYS', 7), // an agency charge issued and still unpaid
    ],

    'staff_roles' => ['super_admin', 'administrator', 'advisor'],

    // Bundled photographs (Unsplash licence: free to use; credit is given as a courtesy). Shown on /image-credits.
    'image_credits' => [
        'images/destinations/ae-2.jpg' => ['name' => 'Sirav Talwar', 'url' => 'https://unsplash.com/photos/dubais-skyline-featuring-the-burj-khalifa-lMo2HjtoUpM', 'subject' => 'Dubai skyline with Burj Khalifa, daytime'],
        'images/destinations/ae-3.jpg' => ['name' => 'Karthik B K', 'url' => 'https://unsplash.com/photos/J1dkREbfefU', 'subject' => 'Abu Dhabi skyline from Corniche Beach'],
        'images/destinations/au.jpg' => ['name' => 'Martijn Vonk', 'url' => 'https://unsplash.com/photos/sydney-opera-house-on-a-clear-day-GmFQLbLLbw4', 'subject' => 'Sydney Opera House and Harbour in late afternoon light'],
        'images/destinations/au-2.jpg' => ['name' => 'John Simmons', 'url' => 'https://unsplash.com/photos/white-bridge-over-river-during-daytime-c5maiKo-vVs', 'subject' => 'Melbourne skyline and the Yarra River'],
        'images/destinations/au-3.jpg' => ['name' => 'Brisbane Local Marketing', 'url' => 'https://unsplash.com/photos/RJII3O7CTeo', 'subject' => 'Brisbane skyline and river'],
        'images/destinations/au-4.jpg' => ['name' => 'Jeremy Huang', 'url' => 'https://unsplash.com/photos/sydney-harbour-bridge-and-opera-house-under-blue-sky-douDlhUfV88', 'subject' => 'Sydney Harbour Bridge and Opera House'],
        'images/destinations/be.jpg' => ['name' => 'Matthieu Joannon', 'url' => 'https://unsplash.com/photos/h3nVbAtszTA', 'subject' => 'Guild houses of the Grand-Place, Brussels (Maison des Brasseurs)'],
        'images/destinations/be-2.jpg' => ['name' => 'Elijah G', 'url' => 'https://unsplash.com/photos/houses-beside-water-stream-KSOl5Y8vqWM', 'subject' => 'Bruges canal with old brick houses'],
        'images/destinations/bn.jpg' => ['name' => 'Hung Li', 'url' => 'https://unsplash.com/photos/a-large-white-building-with-a-gold-dome-sNcCQl2a-_Y', 'subject' => 'Omar Ali Saifuddien Mosque, Bandar Seri Begawan'],
        'images/destinations/br.jpg' => ['name' => 'Davi Costa', 'url' => 'https://unsplash.com/photos/VtecdSFlXsI', 'subject' => 'Rio de Janeiro: Botafogo bay, Sugarloaf Mountain and city'],
        'images/destinations/br-2.jpg' => ['name' => 'Ramon Bucard', 'url' => 'https://unsplash.com/photos/white-concrete-building-near-body-of-water-during-daytime-qL4tAM6uLvc', 'subject' => 'National Congress, Brasilia'],
        'images/destinations/ca.jpg' => ['name' => 'Andrii Khrystian', 'url' => 'https://unsplash.com/photos/5d8RlQJhqtg', 'subject' => 'Toronto skyline with CN Tower across Lake Ontario (wide panorama, about 2.3:1)'],
        'images/destinations/ca-2.jpg' => ['name' => 'Masaru Suzuki', 'url' => 'https://unsplash.com/photos/city-skyline-across-body-of-water-under-blue-and-white-sunny-cloudy-sky-during-daytime-hrPcO1Zarzw', 'subject' => 'Vancouver downtown skyline and Coal Harbour marina from Stanley Park Seawall'],
        'images/destinations/ca-3.jpg' => ['name' => 'Nathan Feyssat', 'url' => 'https://unsplash.com/photos/tKhphNkxK00', 'subject' => 'Chateau Frontenac, Quebec City at sunset (aerial)'],
        'images/destinations/cl.jpg' => ['name' => 'Mauricio Farias', 'url' => 'https://unsplash.com/photos/12-2SSF7OrE', 'subject' => 'Santiago skyline with Gran Torre Costanera and snow-capped Andes, from Cerro San Cristobal'],
        'images/destinations/cl-2.jpg' => ['name' => 'Sheila C', 'url' => 'https://unsplash.com/photos/colorful-houses-climbing-a-hillside-under-a-clear-sky-P2e7rdt7f7Y', 'subject' => 'Valparaiso hillside houses'],
        'images/destinations/cn-2.jpg' => ['name' => 'zhang kaiyv', 'url' => 'https://unsplash.com/photos/oriental-pearl-tower-fliO3C4X31o', 'subject' => 'Shanghai Pudong skyline and Oriental Pearl Tower across the Huangpu River, daytime'],
        'images/destinations/cn-3.jpg' => ['name' => 'Vincent Guth', 'url' => 'https://unsplash.com/photos/forbidden-city-H3tr01sfqGg', 'subject' => 'Hall of Supreme Harmony, Forbidden City, Beijing'],
        'images/destinations/cn-4.jpg' => ['name' => 'Nebular', 'url' => 'https://unsplash.com/photos/a-tall-building-behind-trees-with-leifeng-pagoda-in-the-background-AGjPYeN2Hlo', 'subject' => 'Leifeng Pagoda (West Lake), Hangzhou'],
        'images/destinations/cy.jpg' => ['name' => 'Secret Travel Guide', 'url' => 'https://unsplash.com/photos/aerial-view-of-island-during-daytime-6jxWmNmKM8s', 'subject' => 'Turquoise sea and rocky coast near Paphos, Cyprus'],
        'images/destinations/de-2.jpg' => ['name' => 'Mateo Krossler', 'url' => 'https://unsplash.com/photos/brown-concrete-bridge-over-river-during-daytime-fvJJMbP8-w0', 'subject' => 'Old Bridge and old town, Heidelberg'],
        'images/destinations/de-3.jpg' => ['name' => 'Jared Lisack', 'url' => 'https://unsplash.com/photos/CCs4oZ_qS-o', 'subject' => 'Heidelberg Castle ruins, Heidelberg'],
        'images/destinations/dk.jpg' => ['name' => 'Alain ROUILLER', 'url' => 'https://unsplash.com/photos/DGRAMLiFfrU', 'subject' => 'Nyhavn colourful canal-side houses and sailing boats, Copenhagen'],
        'images/destinations/dk-2.jpg' => ['name' => 'Sandra Gruenewald', 'url' => 'https://unsplash.com/photos/gray-concrete-building-under-blue-sky-and-white-clouds-during-daytime-tKyv6aSwSq8', 'subject' => 'Kronborg Castle, Helsingor'],
        'images/destinations/es-2.jpg' => ['name' => 'Harrison Fitts', 'url' => 'https://unsplash.com/photos/plaza-de-espana-in-seville-VXHqJN52K6s', 'subject' => 'Plaza de Espana, Seville'],
        'images/destinations/fi.jpg' => ['name' => 'Tapio Haaja', 'url' => 'https://unsplash.com/photos/helsinki-cathedral-above-waterfront-in-helsinki-bpDJvls-h-0', 'subject' => 'Helsinki Cathedral above the South Harbour waterfront'],
        'images/destinations/fi-2.jpg' => ['name' => 'Artturi Jalli', 'url' => 'https://unsplash.com/photos/a-body-of-water-surrounded-by-trees-and-clouds-afcKLDme8cM', 'subject' => 'Lake Saimaa, Finnish Lakeland'],
        'images/destinations/gb-2.jpg' => ['name' => 'Gadiel Lazcano', 'url' => 'https://unsplash.com/photos/the-radcliffe-camera-building-is-shown-with-the-sky-kPA7X2BgqV8', 'subject' => 'Radcliffe Camera dome, Oxford'],
        'images/destinations/gb-3.jpg' => ['name' => 'Vraj Patel', 'url' => 'https://unsplash.com/photos/kings-college-chapel-and-university-buildings-by-a-river-FNNL9VNbhqE', 'subject' => 'King\'s College Chapel and Gibbs Building from the River Cam (the Backs), Cambridge'],
        'images/destinations/gb-4.jpg' => ['name' => 'Ben Guerin', 'url' => 'https://unsplash.com/photos/brown-concrete-castle-at-daytime-Y96OcAtUWw0', 'subject' => 'Edinburgh Castle on its rock above the Old Town'],
        'images/destinations/gb-5.jpg' => ['name' => 'Robert Tudor', 'url' => 'https://unsplash.com/photos/london-tower-bridge-taken-under-white-clouds-during-daytime-EjHr26LqfhI', 'subject' => 'Tower Bridge, London, from the Thames South Bank'],
        'images/destinations/gb-6.jpg' => ['name' => 'Mylo Kaye', 'url' => 'https://unsplash.com/photos/a-view-of-a-city-from-the-top-of-a-building-NXtGjC7osPs', 'subject' => 'Manchester skyline at sunrise'],
        'images/destinations/gb-7.jpg' => ['name' => 'Al Elmes', 'url' => 'https://unsplash.com/photos/JcOa7Z5WCbU', 'subject' => 'York Minster, aerial view over York'],
        'images/destinations/gb-8.jpg' => ['name' => 'K. Mitch Hodge', 'url' => 'https://unsplash.com/photos/aerial-view-of-buildings-plDqAUdS-68', 'subject' => 'Conwy Castle and the Conwy estuary, Wales'],
        'images/destinations/hk.jpg' => ['name' => 'Manson', 'url' => 'https://unsplash.com/photos/4vf1KEkD7Gc', 'subject' => 'Hong Kong skyline and Victoria Harbour from above, golden hour'],
        'images/destinations/id.jpg' => ['name' => 'ling hua', 'url' => 'https://unsplash.com/photos/a-large-stone-structure-in-the-middle-of-a-forest-XpaboNNaEYU', 'subject' => 'Borobudur temple, Central Java'],
        'images/destinations/ie.jpg' => ['name' => 'Sophie Popplewell', 'url' => 'https://unsplash.com/photos/a-bridge-over-a-river-blDB0HbjB1k', 'subject' => 'Ha\'penny Bridge over the River Liffey, Dublin'],
        'images/destinations/ie-2.jpg' => ['name' => 'Mick Haupt', 'url' => 'https://unsplash.com/photos/gray-rocky-mountain-beside-blue-sea-under-blue-sky-during-daytime-fXmG986sqnk', 'subject' => 'Cliffs of Moher, County Clare'],
        'images/destinations/ie-3.jpg' => ['name' => 'Clay Banks', 'url' => 'https://unsplash.com/photos/B6I4kHyYQLs', 'subject' => 'Kilkenny Castle courtyard, Kilkenny'],
        'images/destinations/il.jpg' => ['name' => 'Raimond Klavins', 'url' => 'https://unsplash.com/photos/dome-of-the-rock-in-jerusalem-030ZIU1Rook', 'subject' => 'Dome of the Rock and Old City, Jerusalem'],
        'images/destinations/in.jpg' => ['name' => 'shalender kumar', 'url' => 'https://unsplash.com/photos/brown-concrete-arch-under-blue-sky-during-daytime-XjKaPInYVCM', 'subject' => 'India Gate, New Delhi, at dawn under a pink and purple sky'],
        'images/destinations/in-2.jpg' => ['name' => 'Vedanshu Kadu', 'url' => 'https://unsplash.com/photos/the-gateway-of-india-monument-under-a-clear-blue-sky-x26qeiP3ipc', 'subject' => 'Gateway of India, Mumbai'],
        'images/destinations/it-2.jpg' => ['name' => 'Kseniia Zapiatkina', 'url' => 'https://unsplash.com/photos/florence-cityscape-with-duomo-dome-and-tiled-rooftops-DcZld7L2BwM', 'subject' => 'Florence Duomo and rooftops'],
        'images/destinations/jp-2.jpg' => ['name' => 'Charlie Charoenwattana', 'url' => 'https://unsplash.com/photos/golden-pavilion-of-kinkaku-ji-temple-reflecting-on-water-N5SG29ZSz_A', 'subject' => 'Kinkaku-ji (Golden Pavilion), Kyoto'],
        'images/destinations/jp-3.jpg' => ['name' => 'Joss Woodhead', 'url' => 'https://unsplash.com/photos/osaka-castle-rises-above-a-serene-pond-and-green-trees-yj47GYk1g3o', 'subject' => 'Osaka Castle keep above a garden pond'],
        'images/destinations/jp-4.jpg' => ['name' => 'Max Bender', 'url' => 'https://unsplash.com/photos/pagoda-and-mount-fuji-in-japan-FuxYvi-hcWQ', 'subject' => 'Mount Fuji with Chureito Pagoda'],
        'images/destinations/kz-2.jpg' => ['name' => 'Ilyas Dautov', 'url' => 'https://unsplash.com/photos/a-city-with-a-mountain-in-the-background-ljw3mBWuwTA', 'subject' => 'Almaty with Kok Tobe TV tower and Zailiysky Alatau mountains'],
        'images/destinations/kz-3.jpg' => ['name' => 'ekrem osmanoglu', 'url' => 'https://unsplash.com/photos/a-large-white-mosque-with-four-tall-minarets-xjAGf618QT8', 'subject' => 'Hazrat Sultan Mosque, Astana'],
        'images/destinations/kz-4.jpg' => ['name' => 'Tim Broadbent', 'url' => 'https://unsplash.com/photos/uj4TyuvM72w', 'subject' => 'Charyn Canyon'],
        'images/destinations/mx.jpg' => ['name' => 'Azahed', 'url' => 'https://unsplash.com/photos/white-concrete-building-under-blue-sky-during-daytime-nBF5BB13iM0', 'subject' => 'Palacio de Bellas Artes, Mexico City'],
        'images/destinations/my.jpg' => ['name' => 'engin akyurt', 'url' => 'https://unsplash.com/photos/low-angle-photography-of-high-rise-buildings-aONeLHI2KeY', 'subject' => 'Petronas Twin Towers, Kuala Lumpur (daytime, low angle)'],
        'images/destinations/nz-2.jpg' => ['name' => 'Sebastian Schuster', 'url' => 'https://unsplash.com/photos/wellington-cityscape-with-harbor-boats-and-green-hills-kYfGo82fwCA', 'subject' => 'Wellington harbour and CBD from above'],
        'images/destinations/ph.jpg' => ['name' => 'German Rivera De La Torre', 'url' => 'https://unsplash.com/photos/a-stone-gate-with-a-clock-tower-in-the-background-TBDUapl7H_4', 'subject' => 'Fort Santiago gate, Intramuros, Manila'],
        'images/destinations/pk.jpg' => ['name' => 'Dr Muhammad Amer', 'url' => 'https://unsplash.com/photos/badshahi-mosque-in-lahore-pakistan-TXJEdxs5Hh4', 'subject' => 'Badshahi Mosque, Lahore (red sandstone and white domes)'],
        'images/destinations/pk-2.jpg' => ['name' => 'Ali Muhammad', 'url' => 'https://unsplash.com/photos/_YkEkI8tWrQ', 'subject' => 'Faisal Mosque, Islamabad, with the Margalla Hills behind (strong teal colour grade)'],
        'images/destinations/pk-3.jpg' => ['name' => 'Qasim Nagori', 'url' => 'https://unsplash.com/photos/a-river-running-through-a-rocky-valley-surrounded-by-mountains-AjFiqo-PsfE', 'subject' => 'Passu cones and river, Hunza (Gojal valley)'],
        'images/destinations/pl-2.jpg' => ['name' => 'Tomasz Tomal', 'url' => 'https://unsplash.com/photos/a-view-of-a-city-square-with-a-clock-tower-in-the-background-2lK0291iNHI', 'subject' => 'Krakow Main Market Square, St Mary\'s Basilica and Cloth Hall'],
        'images/programmes/aga-khan-foundation-international-scholarship-programme.jpg' => ['name' => 'Zoshua Colah', 'url' => 'https://unsplash.com/photos/students-walk-through-a-beautiful-library-with-bookshelves-75vJyX9e24s', 'subject' => 'Students (seen from behind) in a university library reading room'],
        'images/programmes/erasmus-mundus-joint-masters.jpg' => ['name' => 'Khaled Ali', 'url' => 'https://unsplash.com/photos/a-view-of-a-city-with-a-bridge-and-mountains-in-the-background-YOQyzHQOzRw', 'subject' => 'Heidelberg old town, Old Bridge, Neckar and castle, Germany'],
        'images/programmes/isdb-merit-scholarship-program-for-high-technology-msp-for-isdb-member-countries.jpg' => ['name' => 'Suhrid', 'url' => 'https://unsplash.com/photos/a-beach-next-to-a-city-with-tall-buildings-Zfk19D5hmGc', 'subject' => 'Jeddah Corniche waterfront (Al Shati) with towers and beach'],
        'images/programmes/mastercard-foundation-scholars-program.jpg' => ['name' => 'Thomas Bennie', 'url' => 'https://unsplash.com/photos/city-near-mountain-under-cloudy-sky-during-daytime-cj_f_-OCUWg', 'subject' => 'Cape Town suburbs below Devil\'s Peak, South Africa'],
        'images/programmes/oas-academic-scholarship-program-graduate-studies-and-research-2026-call.jpg' => ['name' => 'Louis Velazquez', 'url' => 'https://unsplash.com/photos/united-states-capitol-building-in-washington-XWW746i6WoM', 'subject' => 'US Capitol, Washington DC, under blue sky'],
        'images/destinations/qa-2.jpg' => ['name' => 'Hongbin', 'url' => 'https://unsplash.com/photos/dhow-boats-near-museum-of-islamic-art-1UF8ddEalwk', 'subject' => 'Museum of Islamic Art and dhows, Doha skyline'],
        'images/destinations/ro.jpg' => ['name' => 'Winston Tjia', 'url' => 'https://unsplash.com/photos/a-large-building-sitting-on-the-side-of-a-road-2D9qjPHazVk', 'subject' => 'Palace of Parliament, Bucharest'],
        'images/destinations/rs.jpg' => ['name' => 'Dimitrije Milenkovic', 'url' => 'https://unsplash.com/photos/an-aerial-view-of-a-city-with-a-castle-XP1ozWBIPb8', 'subject' => 'Kalemegdan fortress, Belgrade'],
        'images/destinations/sa-2.jpg' => ['name' => 'mohammed alorabi', 'url' => 'https://unsplash.com/photos/_ABfNIGGsZk', 'subject' => 'Island (floating) Mosque on the Jeddah Corniche waterfront'],
        'images/destinations/sa-3.jpg' => ['name' => 'Hatem Boukhit', 'url' => 'https://unsplash.com/photos/0nKLqQFGq2s', 'subject' => 'Elephant Rock (Jabal AlFil), AlUla'],
        'images/destinations/sg.jpg' => ['name' => 'Hanna Lazar', 'url' => 'https://unsplash.com/photos/marina-bay-sands-towers-against-a-blue-sky-mdvEpqxw8aM', 'subject' => 'Marina Bay Sands towers against blue sky, Singapore'],
        'images/destinations/sk.jpg' => ['name' => 'Michal Vrba', 'url' => 'https://unsplash.com/photos/zM9iO5wFnp0', 'subject' => 'Bratislava Castle, St Martin\'s Cathedral and old town above the Danube'],
        'images/destinations/th.jpg' => ['name' => 'Anantachai Saothong', 'url' => 'https://unsplash.com/photos/wat-arun-temple-in-bangkok-0DKDTSFBXc8', 'subject' => 'Wat Arun and its central prang on the Chao Phraya River, Bangkok'],
        'images/destinations/tw.jpg' => ['name' => 'Lisanto', 'url' => 'https://unsplash.com/photos/city-skyline-under-blue-sky-and-white-clouds-during-daytime-1gNoIlON3B0', 'subject' => 'Taipei skyline with Taipei 101 seen from the hills'],
        'images/destinations/tw-2.jpg' => ['name' => 'Jonathan Ikemura', 'url' => 'https://unsplash.com/photos/J_Fz8bn9od0', 'subject' => 'Dragon and Tiger Pagodas, Lotus Pond, Kaohsiung'],
        'images/destinations/us-2.jpg' => ['name' => 'Frank (generein)', 'url' => 'https://unsplash.com/photos/vZuuccbr6YU', 'subject' => 'Harvard University across the Charles River, Cambridge, Massachusetts'],
        'images/destinations/us-3.jpg' => ['name' => 'Justin Wolff', 'url' => 'https://unsplash.com/photos/the-golden-gate-bridge-in-san-francisco-california-pbp_Ww2cewA', 'subject' => 'Golden Gate Bridge, San Francisco, in golden light'],
        'images/destinations/us-4.jpg' => ['name' => 'Raja Patel', 'url' => 'https://unsplash.com/photos/chicago-skyline-viewed-across-lake-michigan-on-a-sunny-day-fnfR9uWA8RI', 'subject' => 'Chicago skyline from the Lake Michigan lakefront'],
        'images/destinations/za.jpg' => ['name' => 'Wolfgang Hasselmann', 'url' => 'https://unsplash.com/photos/a-city-with-a-mountain-in-the-background-cQf_eoeiCTc', 'subject' => 'Cape Town below Table Mountain'],
        'images/destinations/za-2.jpg' => ['name' => 'Simon Hurry', 'url' => 'https://unsplash.com/photos/a-view-of-a-city-with-tall-buildings-LuiwD6fnKM8', 'subject' => 'Johannesburg skyline with Hillbrow Tower'],
        'images/destinations/cn.jpg' => ['name' => 'William Olivieri', 'url' => 'https://unsplash.com/photos/great-wall-of-china-on-mountains-HwY9R6_YzgM', 'subject' => 'The Great Wall of China'],
        'images/destinations/de.jpg' => ['name' => 'Arlind Photography', 'url' => 'https://unsplash.com/photos/brandenburg-gate-in-berlin-on-a-sunny-day-ot2mgzwyqLg', 'subject' => 'Brandenburg Gate, Berlin'],
        'images/destinations/tr.jpg' => ['name' => 'Lewis J Goetz', 'url' => 'https://unsplash.com/photos/hagia-sophia-in-istanbul-JbWg7W953LY', 'subject' => 'Hagia Sophia, Istanbul'],
        'images/destinations/gb.jpg' => ['name' => 'Marcin Nowak', 'url' => 'https://unsplash.com/photos/big-ben-london-iXqTqC-f6jI', 'subject' => 'Big Ben and the Houses of Parliament, London'],
        'images/destinations/default.jpg' => ['name' => 'Andrew Stutesman', 'url' => 'https://unsplash.com/photos/blue-green-and-yellow-world-map-l68Z6eF2peA', 'subject' => 'World map with pins'],
        'images/destinations/kr.jpg' => ['name' => 'Ping Onganankun', 'url' => 'https://unsplash.com/photos/crescent-moon-over-seoul-skyline-5htrsUUbFGI', 'subject' => 'Seoul skyline at dusk'],
        'images/destinations/jp.jpg' => ['name' => 'Louie Martinez', 'url' => 'https://unsplash.com/photos/tokyo-tower-illuminated-at-dusk-with-city-skyline-IocJwyqRv3M', 'subject' => 'Tokyo Tower and the city at dusk'],
        'images/destinations/us.jpg' => ['name' => 'Triston Dunn', 'url' => 'https://unsplash.com/photos/empire-state-building-new-york-city-during-daytime-rfj_wOYQkus', 'subject' => 'Empire State Building, New York'],
        'images/destinations/nz.jpg' => ['name' => 'Andreas Sjövall', 'url' => 'https://unsplash.com/photos/landscape-photo-of-mountains-near-body-of-water-during-daytime-ZZUPu8ARD8U', 'subject' => 'Mountains and lake, New Zealand'],
        'images/destinations/ch.jpg' => ['name' => 'LJ Coates', 'url' => 'https://unsplash.com/photos/green-trees-on-mountain-beside-body-of-water-under-blue-sky-during-daytime-vJDqJ6o6Haw', 'subject' => 'Swiss lake and Alps'],
        'images/cities/beijing.jpg' => ['name' => 'Victor He', 'url' => 'https://unsplash.com/photos/temple-of-heaven-in-beijing-0xn9T2cEigE', 'subject' => 'Temple of Heaven, Beijing'],
        'images/cities/shanghai.jpg' => ['name' => 'Edward He', 'url' => 'https://unsplash.com/photos/orient-pearl-shanghai-china-taken-during-daytime-uKyzXEc2k_s', 'subject' => 'Pudong skyline, Shanghai'],
        'images/cities/guangdong.jpg' => ['name' => 'Zheng XUE', 'url' => 'https://unsplash.com/photos/city-skyline-with-modern-skyscrapers-at-sunset-RBxAme1dn6E', 'subject' => 'Guangzhou skyline'],
        'images/cities/guangxi.jpg' => ['name' => 'Sergio Li', 'url' => 'https://unsplash.com/photos/a-person-looking-out-over-li-river-HljGrgrrvxc', 'subject' => 'Li River, Guangxi'],
        'images/cities/zhejiang.jpg' => ['name' => 'Luobing', 'url' => 'https://unsplash.com/photos/a-building-sitting-on-top-of-a-lake-next-to-a-forest-42Ah4zCgT-w', 'subject' => 'Lakeside pavilion, Hangzhou'],
        'images/cities/shaanxi.jpg' => ['name' => 'Yux Xiang', 'url' => 'https://unsplash.com/photos/the-sun-is-setting-over-the-city-skyline-zvVX7prwg4c', 'subject' => 'Xi\'an city wall'],
        'images/cities/chongqing.jpg' => ['name' => 'Paz ifical', 'url' => 'https://unsplash.com/photos/a-city-skyline-at-night-with-a-bridge-in-the-foreground--bOqztU8ndI', 'subject' => 'Chongqing at night'],
        'images/cities/tianjin.jpg' => ['name' => 'Zihan Wong', 'url' => 'https://unsplash.com/photos/a-city-skyline-with-a-ferris-wheel-in-the-foreground-Kzx_xZl8FT4', 'subject' => 'Tianjin Eye and the Hai River'],
        'images/destinations/ae.jpg' => ['name' => 'David Rodrigo', 'url' => 'https://unsplash.com/photos/sheikh-zayed-grand-mosque-in-abu-dhabi-kZ1zThg6G40', 'subject' => 'Sheikh Zayed Grand Mosque, Abu Dhabi'],
        'images/destinations/at.jpg' => ['name' => 'Jacek Dylag', 'url' => 'https://unsplash.com/photos/st-stephens-cathedral-skyline-in-vienna-5SjAaqqCCmY', 'subject' => 'Vienna skyline with St. Stephen\'s Cathedral'],
        'images/destinations/az.jpg' => ['name' => 'Adil Sattarov', 'url' => 'https://unsplash.com/photos/a-group-of-tall-buildings-sitting-next-to-each-other-If5euq-ktso', 'subject' => 'Flame Towers, Baku'],
        'images/destinations/cz.jpg' => ['name' => 'William Zhang', 'url' => 'https://unsplash.com/photos/charles-bridge-and-prague-castle-6En4WYsNYXM', 'subject' => 'Charles Bridge and Prague Castle'],
        'images/destinations/es.jpg' => ['name' => 'Jorge Fernández Salas', 'url' => 'https://unsplash.com/photos/edificio-metropoli-on-gran-via-madrid-ChSZETOal-I', 'subject' => 'Gran Vía, Madrid'],
        'images/destinations/fr.jpg' => ['name' => 'Chris Karidis', 'url' => 'https://unsplash.com/photos/eiffel-tower-paris-france-nnzkZNYWHaU', 'subject' => 'Eiffel Tower, Paris'],
        'images/destinations/hu.jpg' => ['name' => 'Ervin Lukacs', 'url' => 'https://unsplash.com/photos/hungarian-parliament-building-in-budapest-sMyQb3i9bNA', 'subject' => 'Hungarian Parliament, Budapest'],
        'images/destinations/it.jpg' => ['name' => 'David Köhler', 'url' => 'https://unsplash.com/photos/colosseum-arena-photography-VFRTXGw1VjU', 'subject' => 'The Colosseum, Rome'],
        'images/destinations/kz.jpg' => ['name' => 'Viktor Hesse', 'url' => 'https://unsplash.com/photos/a-city-skyline-with-a-fountain-JvQCPAtNMGw', 'subject' => 'Bayterek Tower and skyline, Astana'],
        'images/destinations/nl.jpg' => ['name' => 'Adrien Olichon', 'url' => 'https://unsplash.com/photos/boats-on-canal-in-amsterdam-QRtym77B6xk', 'subject' => 'Canal in Amsterdam'],
        'images/destinations/om.jpg' => ['name' => 'Anfal Shamsudeen', 'url' => 'https://unsplash.com/photos/al-lawatiya-mosque-in-muscat-oman-dZd4XWMiM9g', 'subject' => 'Al Lawatiya Mosque, Muscat'],
        'images/destinations/pl.jpg' => ['name' => 'Maksym Harbar', 'url' => 'https://unsplash.com/photos/people-walking-near-multicolored-building-okn8ZIjPMxI', 'subject' => 'Old Town, Warsaw'],
        'images/destinations/qa.jpg' => ['name' => 'Popup Agency', 'url' => 'https://unsplash.com/photos/katara-towers-in-lusail-qatar-jJtqDVzozQY', 'subject' => 'Katara Towers, Lusail, Qatar'],
        'images/destinations/ru.jpg' => ['name' => 'Dmitry Ant', 'url' => 'https://unsplash.com/photos/a-large-building-with-a-clock-tower-KXCRqEHWM_A', 'subject' => 'The Kremlin and Red Square, Moscow'],
        'images/destinations/sa.jpg' => ['name' => 'Ibrahim Abdullah', 'url' => 'https://unsplash.com/photos/kingdom-centre-skyscraper-in-riyadh-_UxsPARhtOg', 'subject' => 'Kingdom Centre, Riyadh'],
        'images/destinations/se.jpg' => ['name' => 'Ana Bórquez', 'url' => 'https://unsplash.com/photos/white-boat-on-water-near-city-buildings-during-daytime-uF4PfwZPOR8', 'subject' => 'Riddarholmen, Stockholm'],
        'images/destinations/uz.jpg' => ['name' => 'Aleksander Stypczynski', 'url' => 'https://unsplash.com/photos/a-large-building-with-many-pillars-and-arches-l3AwHYiUGG0', 'subject' => 'The Registan, Samarkand'],
        'images/hero-shanghai.jpg' => ['name' => 'Edward He', 'url' => 'https://unsplash.com/photos/orient-pearl-shanghai-china-taken-during-daytime-uKyzXEc2k_s', 'subject' => 'Shanghai skyline (homepage banner)'],
        'images/hero-globe.webp' => ['name' => 'Arpit Rastogi', 'url' => 'https://unsplash.com/photos/white-and-brown-desk-globe-xv7DTjnx2YQ', 'subject' => 'Desk globe (homepage banner, cut out)'],
    ],

    'deadlines' => [
        'reminder_days' => [30, 14, 7, 1], // NOT-01
        'closing_soon_days' => 30,
    ],

    // DATA-04 (configurable operating defaults)
    'review' => [
        'cadence_days' => 30,
        'cadence_days_closing_soon' => 7,
    ],

    /*
     * Two-step sign-in (a 6-digit code from an authenticator app). Off by default: no login page asks for a code, staff included.
     * Turn it on with SAS_TWO_STEP=true: staff must then set it up, and students may opt in on their Security page.
     */
    'two_step' => ['enabled' => (bool) env('SAS_TWO_STEP', false)],

    // Invitation emails an agency may send per day (an agent account must not become a mail relay), and the host names the site answers to.
    'agents' => ['invites_per_day' => (int) env('SAS_AGENT_INVITES_PER_DAY', 200)],
    'trusted_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env('SAS_TRUSTED_HOSTS', ''))))),

    'uploads' => [
        'max_kb' => (int) env('SAS_UPLOAD_MAX_KB', 20480), // FILE-01: 20 MB
        'quota_mb' => (int) env('SAS_UPLOAD_QUOTA_MB', 200), // proposed, pending owner approval
        'extensions' => ['pdf', 'docx', 'jpg', 'jpeg', 'png'],
        'mimes' => [
            'pdf' => ['application/pdf'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
        ],
        'download_ttl_minutes' => 5, // SEC-03
        'disk' => env('SAS_DOCUMENT_DISK', 'private_documents'),
        'scanner' => env('SAS_SCANNER', 'clamav'), // clamav | fake (tests only)
        'clamav_host' => env('CLAMAV_HOST', '127.0.0.1'),
        'clamav_port' => (int) env('CLAMAV_PORT', 3310),
    ],

    // PRIV-03 proposed retention defaults, flagged pending owner / jurisdiction approval.
    'retention' => [
        'approved' => false,
        'abandoned_upload_days' => 30,
        'support_draft_days' => 90,
        'case_document_days_after_closure' => 90,
    ],

    'document_types' => [
        'passport' => 'Passport',
        'transcript' => 'Transcript',
        'degree_certificate' => 'Degree certificate',
        'cv' => 'CV',
        'statement' => 'Statement / motivation letter',
        'language_certificate' => 'Language certificate',
        'recommendation' => 'Recommendation letter',
        'other' => 'Other',
    ],

    'enquiry_categories' => [
        'general' => 'General question',
        'advisory' => 'Scholarship advisory',
        'writing' => 'Writing and applications',
        'document_evaluation' => 'Document evaluation',
        'translation' => 'Translation',
        'attestation' => 'Attestation',
        'admissions' => 'University admissions',
        'visa' => 'Visa assistance',
        'pre_departure' => 'Pre-departure',
        'correction' => 'Report a correction',
    ],
];
