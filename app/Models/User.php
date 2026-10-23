<?php

namespace App\Models;

use App\Domain\Identity\Permissions;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// AUTH-05: only these attributes are mass assignable. Status, roles and MFA columns never are.
#[Fillable(['name', 'email', 'password', 'phone', 'phone_country_code', 'timezone'])]
#[Hidden(['password', 'remember_token', 'app_authentication_secret', 'app_authentication_recovery_codes'])]
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, InteractsWithAppAuthentication, InteractsWithAppAuthenticationRecovery, Notifiable;

    protected $attributes = ['status' => 'active', 'timezone' => 'UTC'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'suspended_at' => 'datetime',
            'password' => 'hashed',
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
        ];
    }

    public function userRoles(): HasMany
    {
        return $this->hasMany(UserRole::class);
    }

    protected static function booted(): void
    {
        static::creating(fn (self $u) => $u->public_id ??= (string) Str::ulid());
    }

    public function partnerOrganisation(): BelongsTo
    {
        return $this->belongsTo(PartnerOrganisation::class, 'partner_organisation_id');
    }

    /** The recruiting agent who looks after this student, if any. */
    public function managedBy(): BelongsTo
    {
        return $this->belongsTo(PartnerOrganisation::class, 'managed_by_organisation_id');
    }

    /** A signed-in partner user of an active recruiting-agent organisation. */
    public function isAgent(): bool
    {
        $org = $this->partner_organisation_id ? $this->partnerOrganisation : null;

        return $this->isActive() && $this->hasRole('partner_manager') && $org && $org->isActive() && $org->kind === 'agent';
    }

    /** Whether this user is an agent who looks after the given student. */
    public function manages(User $student): bool
    {
        return $this->isAgent() && $student->managed_by_organisation_id !== null && $student->managed_by_organisation_id === $this->partner_organisation_id;
    }

    public function applicationPlans(): HasMany
    {
        return $this->hasMany(ApplicationPlan::class);
    }

    public function savedScholarships(): HasMany
    {
        return $this->hasMany(SavedScholarship::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function enquiries(): HasMany
    {
        return $this->hasMany(Enquiry::class);
    }

    public function consultations(): HasMany
    {
        return $this->hasMany(Consultation::class);
    }

    /** AUTH-02: revoke every session when an account is suspended. */
    public function suspend(): void
    {
        $this->forceFill(['status' => 'suspended', 'suspended_at' => now()])->save();
        DB::table('sessions')->where('user_id', $this->id)->delete();
    }

    public function studentProfile(): HasOne
    {
        return $this->hasOne(StudentProfile::class);
    }

    /** @return list<string> */
    public function roleKeys(): array
    {
        return $this->userRoles->pluck('role')->all();
    }

    public function hasRole(Role|string ...$roles): bool
    {
        $keys = $this->roleKeys();
        foreach ($roles as $role) {
            if (in_array($role instanceof Role ? $role->value : $role, $keys, true)) {
                return true;
            }
        }

        return false;
    }

    /** Where this person lands after signing in: staff to the admin panel, partners to the partner area, everyone else to the student workspace. */
    public function homeUrl(): string
    {
        return match (true) {
            $this->isStaff() => '/admin',
            $this->hasRole(Role::PartnerManager) => route('partner.home'),
            default => route('student.dashboard'),
        };
    }

    public function isStaff(): bool
    {
        return $this->isActive() && $this->hasRole(...config('sas.staff_roles'));
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function hasPermission(string $permission): bool
    {
        return $this->isActive() && Permissions::roleKeysGrant($this->roleKeys(), $permission);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isStaff() && $this->hasVerifiedEmail();
    }
}
