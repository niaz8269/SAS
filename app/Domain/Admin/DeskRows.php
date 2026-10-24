<?php

namespace App\Domain\Admin;

use App\Filament\Resources\ApplicationReviews\ApplicationReviewResource;
use App\Filament\Resources\Enquiries\EnquiryResource;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\ServiceCases\ServiceCaseResource;
use App\Filament\Resources\Students\StudentResource;
use App\Models\ApplicationPlan;
use App\Models\Enquiry;
use App\Models\Invoice;
use App\Models\ServiceCase;
use App\Models\Student;
use Illuminate\Support\Str;

/** Turns one row of a Desk group into a line of text and a link, for the Needs attention page and the alert email. */
class DeskRows
{
    /** @return array{label:string,detail:string,url:?string} */
    public static function line(string $group, $row): array
    {
        return match (true) {
            $row instanceof Enquiry => [
                'label' => $row->name.' ('.$row->reference.')',
                'detail' => 'received '.$row->created_at->diffForHumans().($row->owner ? ', owner '.$row->owner->name : ', no owner').': '.Str::limit($row->message, 70),
                'url' => EnquiryResource::getUrl('edit', ['record' => $row]),
            ],
            $row instanceof ServiceCase => [
                'label' => $row->title.' ('.($row->student?->name ?? 'student').')',
                'detail' => $row->statusLabel().', opened '.($row->opened_at?->diffForHumans() ?? '-'),
                'url' => ServiceCaseResource::getUrl('edit', ['record' => $row]),
            ],
            $row instanceof ApplicationPlan => [
                'label' => $row->application_no.': '.($row->user?->name ?? 'student'),
                'detail' => $row->cycle->programme->title.', '.($group === 'returned' ? 'returned '.$row->reviewed_at?->diffForHumans() : 'sent '.($row->review_requested_at?->diffForHumans() ?? '-')),
                'url' => ApplicationReviewResource::getUrl('index'),
            ],
            $row instanceof Invoice => [
                'label' => ($row->serviceCase?->title ?? 'Case').' ('.($row->student?->name ?? 'student').')',
                'detail' => $row->amount().', '.($group === 'refunds' ? 'refund '.$row->refundAmount().' due' : 'issued '.($row->issued_at?->diffForHumans() ?? '-')),
                'url' => InvoiceResource::getUrl('index'),
            ],
            $row instanceof Student => [
                'label' => $row->name,
                'detail' => $row->waiting_since ? 'wrote '.$row->waiting_since->diffForHumans().($row->counsellor ? ', counsellor '.$row->counsellor->name : ', no counsellor') : 'registered '.$row->created_at->diffForHumans(),
                'url' => StudentResource::getUrl('view', ['record' => $row]),
            ],
            default => ['label' => (string) $group, 'detail' => '', 'url' => null],
        };
    }
}
