<?php

namespace App\Domain\Counselling;

use App\Domain\Editorial\AuditLog;
use App\Models\AvailabilitySlot;
use App\Models\Consultation;
use App\Models\Student;
use App\Models\User;
use App\Notifications\CallBooked;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Counsellors offer free times; students book one; the booking becomes a confirmed consultation in the counsellor's calendar. */
class Booking
{
    public const MAX_NEW_SLOTS = 40;

    /** Adds back-to-back slots between two clock times on one day, in the counsellor's own time zone. @return int how many were added */
    public function addSlots(User $counsellor, string $date, string $from, string $to, int $minutes): int
    {
        if ($minutes < 10 || $minutes > 240) {
            throw new InvalidArgumentException('A call is between 10 and 240 minutes.');
        }
        $tz = $counsellor->timezone ?: 'UTC';
        $start = Carbon::parse("$date $from", $tz);
        $end = Carbon::parse("$date $to", $tz);
        if ($end->lte($start)) {
            throw new InvalidArgumentException('The end time must be after the start time.');
        }
        $added = 0;
        for ($t = $start->copy(); $t->copy()->addMinutes($minutes)->lte($end) && $added < self::MAX_NEW_SLOTS; $t->addMinutes($minutes)) {
            if ($t->lte(now())) {
                continue;
            }
            $slot = AvailabilitySlot::firstOrNew(['counsellor_id' => $counsellor->id, 'starts_at' => $t->copy()->utc()]);
            if (! $slot->exists) {
                $slot->forceFill(['counsellor_id' => $counsellor->id, 'duration_minutes' => $minutes])->save();
                $added++;
            }
        }

        return $added;
    }

    /** Free slots a student may book: their counsellor's, or anyone's when they have none yet. @return Collection<int,AvailabilitySlot> */
    public function freeFor(User $student, int $days = 21): Collection
    {
        $own = $student->counsellor_id ?: Student::find($student->id)?->cases()->whereNotNull('advisor_id')->latest('id')->value('advisor_id');

        return AvailabilitySlot::free()->where('starts_at', '<', now()->addDays($days))->with('counsellor')
            ->when($own, fn ($q) => $q->where('counsellor_id', $own))->orderBy('starts_at')->get();
    }

    public function book(AvailabilitySlot $slot, User $student, ?string $note = null): Consultation
    {
        return DB::transaction(function () use ($slot, $student, $note) {
            $slot = AvailabilitySlot::lockForUpdate()->findOrFail($slot->id);
            if ($slot->isBooked() || ! $slot->starts_at->gt(now()->addHours(2))) {
                throw new InvalidArgumentException('That time is no longer available. Please choose another.');
            }
            // A student with a counsellor books that counsellor's times, not anybody else's (the list already shows only those).
            $own = $student->counsellor_id ?: Student::find($student->id)?->cases()->whereNotNull('advisor_id')->latest('id')->value('advisor_id');
            if ($own && $slot->counsellor_id !== (int) $own) {
                throw new InvalidArgumentException('That time belongs to another counsellor. Please choose one of your own counsellor\'s times.');
            }
            if (AvailabilitySlot::where('booked_by', $student->id)->where('starts_at', '>', now())->exists()) {
                throw new InvalidArgumentException('You already have a call booked. Cancel it first if you want to change the time.');
            }
            $consultation = new Consultation(['name' => $student->name, 'email' => $student->email, 'phone' => $student->phone, 'preferred_from' => $slot->starts_at->toDateString(), 'student_timezone' => $student->timezone ?: 'UTC', 'contact_method' => 'email', 'notes' => $note]);
            $consultation->forceFill(['user_id' => $student->id, 'advisor_id' => $slot->counsellor_id, 'status' => 'confirmed', 'confirmed_start_utc' => $slot->starts_at, 'duration_minutes' => $slot->duration_minutes])->save();
            $slot->forceFill(['booked_by' => $student->id, 'consultation_id' => $consultation->id, 'booked_at' => now()])->save();
            AuditLog::record('call.booked', $consultation, null, ['slot' => $slot->id], null, $student);
            $slot->counsellor->notify(new CallBooked($student, $slot, $note));

            return $consultation;
        });
    }

    /** The student cancels up to 24 hours before; the time is offered again. */
    public function cancel(AvailabilitySlot $slot, User $student): void
    {
        if ($slot->booked_by !== $student->id) {
            throw new InvalidArgumentException('That is not your booking.');
        }
        if (! $slot->starts_at->gt(now()->addDay())) {
            throw new InvalidArgumentException('A call can only be cancelled up to 24 hours before it starts. Please message your counsellor.');
        }
        DB::transaction(function () use ($slot, $student) {
            $slot->consultation?->forceFill(['status' => 'cancelled'])->save();
            $slot->forceFill(['booked_by' => null, 'consultation_id' => null, 'booked_at' => null])->save();
            AuditLog::record('call.cancelled', $slot, null, null, null, $student);
        });
    }
}
