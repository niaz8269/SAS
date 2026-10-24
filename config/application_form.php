<?php

/*
 * The master application form, modelled on the China Scholarship Council application form.
 * Sections hold fields; a field is [label, type, options?, required?]. Types: text, date, email, textarea, select, yesno, country.
 * A "repeat" section holds up to `max` rows of the same fields (other education, employment).
 * `required` fields are the ones a counsellor needs before the form counts as complete; saving is always allowed.
 */
return [
    'sections' => [
        'personal' => ['title' => 'Personal information', 'fields' => [
            'surname' => ['Surname (as in passport)', 'text', 'required' => true],
            'given_name' => ['Given name (as in passport)', 'text', 'required' => true],
            'chinese_name' => ['Chinese name (if any)', 'text'],
            'gender' => ['Gender', 'select', 'options' => ['Male', 'Female'], 'required' => true],
            'date_of_birth' => ['Date of birth', 'date', 'required' => true],
            'marital_status' => ['Marital status', 'select', 'options' => ['Single', 'Married', 'Divorced', 'Widowed']],
            'religion' => ['Religion (optional)', 'text'],
            'nationality' => ['Nationality', 'country', 'required' => true],
            'native_language' => ['Native language', 'text'],
            'birth_country' => ['Country of birth', 'country', 'required' => true],
            'birth_city' => ['City of birth', 'text'],
            'passport_no' => ['Passport number', 'text', 'required' => true],
            'passport_expiry' => ['Passport expiry date', 'date', 'required' => true],
        ]],
        'contact' => ['title' => 'Your contact details', 'fields' => [
            'phone' => ['Phone', 'text', 'required' => true],
            'email' => ['Email', 'email', 'required' => true],
            'alt_email' => ['Alternate email', 'email'],
            'skype' => ['Skype', 'text'],
            'wechat' => ['WeChat ID', 'text'],
            'address' => ['Correspondence address', 'textarea', 'required' => true],
        ]],
        'emergency' => ['title' => 'Emergency contact', 'fields' => [
            'em_name' => ['Name', 'text', 'required' => true],
            'em_gender' => ['Gender', 'select', 'options' => ['Male', 'Female']],
            'em_relation' => ['Relation to you', 'text', 'required' => true],
            'em_email' => ['Email', 'email'],
            'em_phone' => ['Phone', 'text', 'required' => true],
            'em_address' => ['Correspondence address', 'textarea'],
        ]],
        'education' => ['title' => 'Highest or current education', 'fields' => [
            'edu_level' => ['Education level', 'select', 'options' => ['Senior high school', 'Bachelor', 'Master', 'Doctoral', 'Other'], 'required' => true],
            'edu_country' => ['Country of the institute', 'country', 'required' => true],
            'edu_institute' => ['Institute name', 'text', 'required' => true],
            'edu_from' => ['Studied from (day, month, year)', 'date', 'required' => true],
            'edu_to' => ['Studied until (day, month, year; expected end if still studying)', 'date', 'required' => true],
            'edu_field' => ['Field of study', 'text'],
            'edu_qualification' => ['Qualification (for example BSc)', 'text'],
        ]],
        // SAS needs at least two education records: the one above and entry 1 here. required_first lists the fields entry 1 must have; a later entry that is started must have them too.
        'other_education' => ['title' => 'Other education certificates', 'repeat' => 2, 'required_first' => ['level', 'institute', 'from', 'to'],
            'note' => 'Add at least one more education record besides the one above (for example your earlier degree or your high school). SAS needs two.', 'fields' => [
            'level' => ['Education level', 'select', 'options' => ['Senior high school', 'Bachelor', 'Master', 'Doctoral', 'Other']],
            'country' => ['Country of the institute', 'country'],
            'institute' => ['Institute name', 'text'],
            'from' => ['Studied from (day, month, year)', 'date'],
            'to' => ['Studied until (day, month, year)', 'date'],
            'field' => ['Field of study', 'text'],
            'qualification' => ['Qualification', 'text'],
        ]],
        'employment' => ['title' => 'Employment history', 'repeat' => 3, 'fields' => [
            'employer' => ['Employer', 'text'],
            'duration' => ['Duration (for example 2021 to 2023)', 'text'],
            'position' => ['Title and position', 'text'],
        ]],
        'language' => ['title' => 'English', 'fields' => [
            'english_holder' => ['Do you hold an English proficiency certificate or letter?', 'yesno'],
            'english_test' => ['Name of the test or letter', 'text'],
            'english_score' => ['Score obtained', 'text'],
            'english_date' => ['Test or letter date', 'date'],
            'english_level' => ['Your English level', 'select', 'options' => ['None', 'Beginner', 'Intermediate', 'Advanced', 'Native']],
        ]],
        'other_languages' => ['title' => 'Other languages you know', 'repeat' => 3, 'fields' => [
            'language' => ['Language (for example Chinese, Turkish, German)', 'text'],
            'level' => ['Your level', 'select', 'options' => ['Beginner', 'Intermediate', 'Advanced', 'Native']],
            'certificate' => ['Certificate or test, if any (for example HSK, TestDaF)', 'text'],
            'score' => ['Level or score obtained', 'text'],
            'date' => ['Test date', 'date'],
        ]],
        'study_plan' => ['title' => 'Study plan', 'fields' => [
            'apply_as' => ['Apply as (for example bachelor, master, doctoral)', 'text', 'required' => true],
            'discipline' => ['Discipline applying for', 'text'],
            'major' => ['Major applying for', 'text', 'required' => true],
            'major_duration' => ['Duration of the major', 'text'],
        ]],
        'china_history' => ['title' => 'Study or work in China', 'fields' => [
            'in_china' => ['Have you ever studied or worked in China?', 'yesno'],
            'in_china_where' => ['Institute or employer, and duration', 'textarea'],
            'csc_before' => ['Have you studied in China under a Chinese Government Scholarship?', 'yesno'],
            'csc_before_where' => ['Institute name and duration', 'textarea'],
        ]],
        'china_contact' => ['title' => 'Contact person in China', 'fields' => [
            'cn_name' => ['Name of contact person or organisation', 'text'],
            'cn_tel' => ['Phone', 'text'],
            'cn_email' => ['Email', 'email'],
            'cn_fax' => ['Fax', 'text'],
            'cn_address' => ['Address', 'textarea'],
        ]],
        'family' => ['title' => 'Family', 'fields' => [
            'spouse_name' => ['Spouse: name', 'text'],
            'spouse_occupation' => ['Spouse: occupation', 'text'],
            'spouse_age' => ['Spouse: age', 'text'],
            'father_name' => ['Father: name', 'text'],
            'father_occupation' => ['Father: occupation', 'text'],
            'father_age' => ['Father: age', 'text'],
            'mother_name' => ['Mother: name', 'text'],
            'mother_occupation' => ['Mother: occupation', 'text'],
            'mother_age' => ['Mother: age', 'text'],
        ]],
    ],

    // The applicant declaration on the CSC form. Ticking it records the date; it is part of the "complete" check.
    'declaration' => 'All the information and documents I provide for my applications are complete, true and correct. I understand that false information can disqualify me.',
];
