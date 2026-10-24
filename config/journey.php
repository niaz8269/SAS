<?php

/*
 * What happens to an application after the student and SAS have prepared it. The first two stages follow the application itself
 * (unfinished or returned, then waiting for SAS review); from "ready" on, SAS (the counsellor or an admin) moves the application along.
 * Each stage lists the tasks the student gets when it starts. SAS can add its own tasks with a date.
 */
return [
    'stages' => [
        'applying' => ['label' => 'Preparing the application', 'hint' => 'Fill in the form and upload the documents.', 'tasks' => []],
        'review' => ['label' => 'SAS is reviewing it', 'hint' => 'SAS checks the form and documents and passes or returns the application.', 'tasks' => []],
        'ready' => ['label' => 'Review passed', 'hint' => 'SAS prepares to send the application to the funder or university.', 'tasks' => ['Read the remarks from SAS, if there are any']],
        'funder' => ['label' => 'Submitted to the funder', 'hint' => 'Now the funder or university decides. Keep your phone and email open.', 'tasks' => ['Watch your email, including spam, for the funder', 'Send any extra document the funder asks for']],
        'interview' => ['label' => 'Interview or assessment', 'hint' => 'Some funders interview or test the shortlisted students.', 'tasks' => ['Prepare for the interview or test', 'Take part on the date you are given']],
        'offer' => ['label' => 'Offer received', 'hint' => 'You have an offer. Read it carefully before you answer.', 'tasks' => ['Read the offer and its conditions', 'Accept or decline before the date on the offer', 'Send the signed acceptance to SAS']],
        'visa' => ['label' => 'Visa and travel', 'hint' => 'Visa, medical check and your journey.', 'tasks' => ['Do the medical examination if it is required', 'Apply for the visa', 'Book your ticket', 'Tell SAS your arrival date']],
        'arrived' => ['label' => 'Arrived and enrolled', 'hint' => 'You are at the university.', 'tasks' => ['Register at the university', 'Send SAS a short update']],
        'closed' => ['label' => 'Finished or stopped', 'hint' => 'This application is finished.', 'tasks' => []],
    ],
    // The stages SAS can set by hand, in order.
    'staff_stages' => ['ready', 'funder', 'interview', 'offer', 'visa', 'arrived', 'closed'],
];