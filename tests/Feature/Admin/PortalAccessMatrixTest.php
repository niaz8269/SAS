<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\PartnerOrganisations\PartnerOrganisationResource;
use App\Filament\Resources\PartnerSubmissions\PartnerSubmissionResource;
use App\Filament\Resources\PaymentOptions\PaymentOptionResource;
use App\Filament\Resources\ServiceCases\ServiceCaseResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Who can open which admin screen, for every staff role. A screen a role must not see answers 403 (and is hidden from its menu). */
class PortalAccessMatrixTest extends TestCase
{
    use RefreshDatabase;

    private function as(Role $role): void
    {
        Filament::setCurrentPanel('admin');
        $user = User::factory()->role($role)->create();
        $user->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP', 'app_authentication_recovery_codes' => ['aaaa-bbbb']])->save();
        $this->actingAs($user);
    }

    /** @return array<string, array{0: Role, 1: array<string,bool>}> */
    public static function matrix(): array
    {
        $all = ['cases' => true, 'invoices' => true, 'partners' => true, 'partnerSubmissions' => true, 'payments' => true, 'users' => true];
        $none = array_map(fn () => false, $all);

        return [
            'super admin' => [Role::SuperAdmin, $all],
            'administrator' => [Role::Administrator, ['users' => true] + $all],
            'service manager' => [Role::ServiceManager, ['payments' => false, 'users' => false] + $all],
            'advisor' => [Role::Advisor, ['cases' => true] + ['invoices' => true] + $none], // sees only assigned cases and their fees
            'specialist' => [Role::Specialist, ['cases' => true, 'invoices' => true] + $none],
            'finance' => [Role::Finance, ['invoices' => true] + $none],
            'editor' => [Role::Editor, $none],
            'reviewer' => [Role::Reviewer, $none],
        ];
    }

    #[DataProvider('matrix')]
    public function test_each_role_sees_exactly_its_own_screens(Role $role, array $expected): void
    {
        $this->as($role);
        $actual = [
            'cases' => ServiceCaseResource::canViewAny(),
            'invoices' => InvoiceResource::canViewAny(),
            'partners' => PartnerOrganisationResource::canViewAny(),
            'partnerSubmissions' => PartnerSubmissionResource::canViewAny(),
            'payments' => PaymentOptionResource::canViewAny(),
            'users' => UserResource::canViewAny(),
        ];
        foreach ($expected as $screen => $should) {
            $this->assertSame($should, $actual[$screen], "{$role->value} / {$screen}");
        }
    }

    public function test_only_finance_and_managers_can_record_payments_not_advisors(): void
    {
        $this->as(Role::Advisor);
        $this->assertFalse(auth()->user()->can('invoices.manage'));
        auth()->logout();
        $this->as(Role::Finance);
        $this->assertTrue(auth()->user()->can('invoices.manage'));
        $this->assertFalse(auth()->user()->can('cases.manage'), 'finance does not run cases');
    }
}
