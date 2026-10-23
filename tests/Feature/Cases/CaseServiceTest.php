<?php

namespace Tests\Feature\Cases;

use App\Domain\Cases\CaseService;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class CaseServiceTest extends TestCase
{
    use RefreshDatabase;

    private function open(): array
    {
        $student = User::factory()->create();
        $advisor = User::factory()->role(Role::Advisor)->create();
        $case = app(CaseService::class)->open($student, null, $advisor, ['title' => 'Yenching Fellowship']);

        return [$case, $student, $advisor];
    }

    public function test_opening_a_case_issues_the_application_fee(): void
    {
        [$case, $student] = $this->open();
        $invoice = $case->invoices()->sole();
        $this->assertSame('application_fee', $invoice->kind);
        $this->assertSame(10000, $invoice->amount_cents);
        $this->assertSame('US$100', $invoice->amount());
        $this->assertSame($student->id, $invoice->user_id);
        $this->assertSame('new', $case->status);
    }

    public function test_a_rejected_application_refunds_half_of_a_paid_application_fee_only(): void
    {
        [$case] = $this->open();
        $svc = app(CaseService::class);
        $svc->markPaid($case->invoices()->first(), null, 'BANK-1');
        $svc->setStatus($case, 'in_progress');
        $svc->setStatus($case, 'submitted');
        $svc->setStatus($case, 'rejected');

        $fee = $case->invoices()->first()->refresh();
        $this->assertSame('paid', $fee->status);
        $this->assertSame(5000, $fee->refund_cents);
        $this->assertSame('due', $fee->refund_status);
        $this->assertCount(1, $case->invoices()->get(), 'no agency charge is issued for a rejection');

        $svc->markRefunded($fee);
        $this->assertSame('paid', $fee->refresh()->refund_status);
    }

    public function test_an_accepted_application_issues_the_agency_charge_once(): void
    {
        [$case] = $this->open();
        $svc = app(CaseService::class);
        $svc->setStatus($case, 'in_progress');
        $svc->setStatus($case, 'submitted');
        $svc->setStatus($case, 'accepted');

        $charges = $case->invoices()->where('kind', 'agency_charge')->get();
        $this->assertCount(1, $charges);
        $this->assertSame(50000, $charges->first()->amount_cents);
        $this->assertSame('none', $case->invoices()->first()->refund_status);
    }

    public function test_withdrawing_before_work_starts_refunds_the_whole_fee_and_afterwards_nothing(): void
    {
        [$early] = $this->open();
        $svc = app(CaseService::class);
        $svc->markPaid($early->invoices()->first(), null, null);
        $svc->setStatus($early, 'withdrawn');
        $this->assertSame(10000, $early->invoices()->first()->refund_cents);

        [$late] = $this->open();
        $svc->markPaid($late->invoices()->first(), null, null);
        $svc->setStatus($late, 'in_progress');
        $svc->setStatus($late, 'withdrawn');
        $this->assertSame(0, $late->invoices()->first()->refund_cents);
        $this->assertSame('none', $late->invoices()->first()->refund_status);
    }

    public function test_an_unpaid_fee_is_cancelled_not_refunded_and_bad_moves_are_refused(): void
    {
        [$case] = $this->open();
        $svc = app(CaseService::class);
        $svc->setStatus($case, 'withdrawn');
        $this->assertSame('void', $case->invoices()->first()->status);

        $this->expectException(InvalidArgumentException::class);
        $svc->setStatus($case, 'accepted');
    }
}
