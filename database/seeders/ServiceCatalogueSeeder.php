<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * SVC-01 service catalogue drafts. Wording follows the specification's deliverables and boundaries. No prices, no turnaround
 * promises, no success claims. Seeded UNPUBLISHED: the owner approves scope text before each page goes live (§24.3).
 */
class ServiceCatalogueSeeder extends Seeder
{
    public function run(): void
    {
        $catalogue = [
            ['advisory', 'Scholarship Advisory', 'Personalised guidance to help you find suitable opportunities.', 'Students deciding where and how to apply.',
                ['Profile review', 'Scholarship shortlist', 'Action plan'], ['No guarantee of an award'], ['Academic background', 'Study goals', 'Budget and timing']],
            ['writing', 'Writing and Applications', 'Expert support for statements of purpose, CVs, motivation letters and proposals.', 'Students preparing application documents.',
                ['Feedback on drafts', 'Editing of SOP, CV or motivation letter', 'Proposal feedback'], ['You supply truthful experience; we never invent credentials, references or results'],
                ['Target award or university', 'Prompt and word limit', 'Relevant experience and goals', 'Required format and deadline']],
            ['application_review', 'Application Review', 'A completeness check of your forms and attachments before you submit.', 'Students who have prepared an application.',
                ['Form and attachment completeness review'], ['Official submission is done by you on the provider website'], ['Application draft', 'Requirements list']],
            ['document_evaluation', 'Document Evaluation', 'A structured review of your documents against the stated requirements.', 'Students with transcripts, certificates and letters.',
                ['Result per document: complete, missing, unclear, inconsistent or requires authorised assessment'], ['This is a service review, not official equivalency or recognition by a government or university'], ['Documents and the requirements they must meet']],
            ['translation', 'Translation', 'Translated documents, with certification if ordered.', 'Students whose documents are not in the required language.',
                ['Translated documents', 'Certification where ordered'], ['Language pair and provider scope are confirmed before work starts'], ['Clear copies of documents', 'Target language']],
            ['attestation', 'Attestation Support', 'Checklist, coordination and status updates for document attestation.', 'Students whose documents need attestation or legalisation.',
                ['Step checklist', 'Coordination and status updates', 'Record of any original documents we handle'], ['Decisions remain with the issuing authority; external fees and timing are outside our control'], ['Document types', 'Issuing country', 'Destination country']],
            ['admissions', 'University Admissions', 'Programme selection and application support.', 'Students applying to universities.',
                ['Programme shortlist', 'Application guidance'], ['The institution makes the admission decision'], ['Academic background', 'Preferred subjects and countries']],
            ['visa', 'Visa Assistance', 'Checklist, form review and interview preparation.', 'Students with an offer who need a study visa.',
                ['Checklist', 'Form review', 'Interview preparation'], ['No guarantee of visa approval; advice is based on official guidance we date and cite'], ['Offer reference', 'Destination', 'Application stage']],
            ['pre_departure', 'Pre-departure Support', 'Guidance on arrival, insurance and housing.', 'Students preparing to travel.',
                ['Arrival checklist', 'Insurance and housing guidance'], ['We do not book travel by default'], ['Destination', 'Arrival date']],
        ];

        foreach ($catalogue as $i => [$cat, $name, $summary, $audience, $deliverables, $exclusions, $inputs]) {
            Service::firstOrCreate(['slug' => Str::slug($name)], [
                'category' => $cat, 'name' => $name, 'summary' => $summary, 'audience' => $audience,
                'deliverables' => $deliverables, 'exclusions' => $exclusions, 'required_inputs' => $inputs,
                'process_steps' => ['Send a free enquiry', 'We review it and confirm scope, deliverables and any fee with you', 'Work starts once you agree'],
                'price_basis' => null, 'turnaround_basis' => null, 'published' => false, 'featured' => false, 'sort' => $i,
            ]);
        }
    }
}
