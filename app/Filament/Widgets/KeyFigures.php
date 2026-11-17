<?php

namespace App\Filament\Widgets;

use App\Enums\EnquiryStatus;
use App\Filament\Resources\ApplicationReviews\ApplicationReviewResource;
use App\Filament\Resources\Enquiries\EnquiryResource;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Students\StudentResource;
use App\Models\ApplicationPlan;
use App\Models\Enquiry;
use App\Models\Invoice;
use App\Models\Student;
use Filament\Widgets\Widget;
use Illuminate\Support\Str;

/** Five numbers at the top of the dashboard. Each card appears only for staff allowed to open the screen behind it, and links there. */
class KeyFigures extends Widget
{
    protected static ?int $sort = 0;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.key-figures';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->isStaff();
    }

    protected function getViewData(): array
    {
        $user = auth()->user();
        $cards = [];

        if ($user->can('enquiries.view_all')) {
            $open = Enquiry::whereNotIn('status', [EnquiryStatus::Closed->value, EnquiryStatus::Converted->value])->count();
            $unassigned = Enquiry::whereNull('owner_id')->whereNotIn('status', [EnquiryStatus::Closed->value, EnquiryStatus::Converted->value])->count();
            $cards[] = ['label' => 'Open enquiries', 'value' => number_format($open), 'note' => $unassigned ? $unassigned.' unassigned' : 'All have an owner', 'icon' => 'users', 'tone' => $unassigned ? 'warn' : '', 'url' => EnquiryResource::getUrl()];

            $total = Enquiry::count();
            $converted = Enquiry::where('status', EnquiryStatus::Converted->value)->count();
            $cards[] = ['label' => 'Conversion', 'value' => $total ? round($converted / $total * 100).'%' : '0%', 'note' => number_format($converted).' of '.number_format($total).' became students', 'icon' => 'trend', 'tone' => '', 'url' => EnquiryResource::getUrl()];
        }
        if ($user->can('students.manage') || $user->can('students.assigned')) {
            $mine = $user->can('students.manage') ? Student::query() : Student::counselledBy($user);
            $new = (clone $mine)->where('users.created_at', '>=', now()->startOfMonth())->count();
            $cards[] = ['label' => $user->can('students.manage') ? 'Students' : 'My students', 'value' => number_format((clone $mine)->count()), 'note' => $new ? $new.' new this month' : 'None new this month', 'icon' => 'cap', 'tone' => '', 'url' => StudentResource::getUrl()];
        }
        if ($user->can('cases.manage')) {
            $review = ApplicationPlan::where('stage', 'submitted')->count();
            $active = ApplicationPlan::whereNotIn('status', ['withdrawn', 'unsuccessful'])->count();
            $cards[] = ['label' => 'Applications to review', 'value' => number_format($review), 'note' => number_format($active).' active in total', 'icon' => 'doc', 'tone' => $review ? 'warn' : '', 'url' => ApplicationReviewResource::getUrl()];
        }
        if ($user->can('finance.view')) {
            $issued = Invoice::where('status', 'issued');
            $cards[] = ['label' => 'Awaiting payment', 'value' => Invoice::money((int) (clone $issued)->sum('amount_cents')), 'note' => (clone $issued)->count().' '.Str::plural('invoice', (clone $issued)->count()), 'icon' => 'cash', 'tone' => '', 'url' => InvoiceResource::getUrl()];
        }

        return ['cards' => $cards];
    }
}
