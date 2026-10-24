<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A user who is only a student (no staff role, not a partner user), as staff see them under Students. Same table and rows as User. */
class Student extends User
{
    protected $table = 'users';

    protected static function booted(): void
    {
        parent::booted();
        static::addGlobalScope('students', fn (Builder $q) => $q->whereDoesntHave('userRoles')->whereNull('partner_organisation_id'));
    }

    /** Relations inherited from User (documents, applications...) are keyed by user_id, not student_id. */
    public function getForeignKey(): string
    {
        return 'user_id';
    }

    public function cases(): HasMany
    {
        return $this->hasMany(ServiceCase::class, 'user_id');
    }

    public function getMorphClass(): string
    {
        return User::class; // audit entries and relations keep pointing at the user
    }
}
