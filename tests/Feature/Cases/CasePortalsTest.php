<?php

namespace Tests\Feature\Cases;

use App\Domain\Cases\CaseService;
use App\Enums\Role;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\ServiceCases\Pages\CreateServiceCase;
use App\Filament\Resources\ServiceCases\Pages\EditServiceCase;
use App\Filament\Resources\ServiceCases\Pages\ListServiceCases;
use App\Filament\Resources\ServiceCases\RelationManagers\InvoicesRelationManager;
use App\Models\Invoice;
use App\Models\ServiceCase;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CasePortalsTest extends TestCase
{
    use RefreshDatabase;

    private function staff(Role $role): User
    {
        Filament::setCurrentPanel('admin');
        $user = User::factory()->role($role)->create();
        $user->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP', 'app_authentication_recovery_codes' => ['aaaa-bbbb']])->save();
        $this->actingAs($user);

        return $user;
    }

    public function test_a_student_sees_only_their_own_cases_and_fees(): void
    {
        $mine = User::factory()->create();
        $other = User::factory()->create();
        $case = app(CaseService::class)->open($mine, null, null, ['title' => 'Chevening application']);
        $theirs = app(CaseService::class)->open($other, null, null, ['title' => 'Secret other case']);

        $this->actingAs($mine)->get(route('student.cases.index'))->assertOk()->assertSee('Chevening application')->assertSee('US$100')->assertDontSee('Secret other case');
        $this->get(route('student.cases.show', $case))->assertOk()->assertSee('Application fee')->assertSee('Awaiting payment')->assertSee('How to pay');
        $this->get(route('student.cases.show', $theirs))->assertForbidden();
    }

    public function test_a_manager_opens_a_case_and_the_application_fee_is_issued(): void
    {
        $student = User::factory()->create();
        $this->staff(Role::Administrator);
        Livewire::test(CreateServiceCase::class)->fillForm(['user_id' => $student->id, 'title' => 'DAAD Master'])->call('create')->assertHasNoFormErrors();
        $case = ServiceCase::firstWhere('title', 'DAAD Master');
        $this->assertNotNull($case);
        $this->assertSame($student->id, $case->user_id);
        $this->assertSame(1, $case->invoices()->where('kind', 'application_fee')->count());
    }

    public function test_status_changes_and_payments_work_from_the_admin(): void
    {
        $student = User::factory()->create();
        $manager = $this->staff(Role::Administrator);
        $case = app(CaseService::class)->open($student, null, null, ['title' => 'Case A'], $manager);
        $invoice = $case->invoices()->first();

        Livewire::test(InvoicesRelationManager::class, ['ownerRecord' => $case, 'pageClass' => EditServiceCase::class])
            ->callTableAction('markPaid', $invoice, ['reference' => 'WIRE-7']);
        $this->assertSame('paid', $invoice->refresh()->status);
        $this->assertSame('WIRE-7', $invoice->payment_reference);

        Livewire::test(EditServiceCase::class, ['record' => $case->getRouteKey()])->callAction('changeStatus', ['status' => 'in_progress']);
        $this->assertSame('in_progress', $case->refresh()->status);
    }

    public function test_an_advisor_sees_only_assigned_cases_and_cannot_record_payments(): void
    {
        $advisor = $this->staff(Role::Advisor);
        $mine = app(CaseService::class)->open(User::factory()->create(), null, $advisor, ['title' => 'Mine']);
        $notMine = app(CaseService::class)->open(User::factory()->create(), null, null, ['title' => 'Not mine']);

        Livewire::test(ListServiceCases::class)->assertCanSeeTableRecords([$mine])->assertCanNotSeeTableRecords([$notMine]);
        $this->assertTrue($advisor->can('view', $mine));
        $this->assertFalse($advisor->can('view', $notMine));
        $this->assertFalse($advisor->can('update', $mine->invoices()->first()), 'only finance and managers record payments');
    }

    public function test_admins_run_the_invoices_but_counsellors_can_only_read_their_own(): void
    {
        $finance = $this->staff(Role::Administrator);
        $this->assertTrue($finance->can('viewAny', Invoice::class));
        $this->assertTrue($finance->can('update', app(CaseService::class)->open(User::factory()->create(), null, null, ['title' => 'X'])->invoices()->first()));
        $this->assertTrue(InvoiceResource::canViewAny());

        auth()->logout();
        $counsellor = $this->staff(Role::Advisor);
        $invoice = Invoice::first();
        $this->assertFalse($counsellor->can('update', $invoice), 'a counsellor never records payments');
        $this->assertFalse($counsellor->can('view', $invoice), 'and only reads the fees of cases assigned to them');
    }
}
