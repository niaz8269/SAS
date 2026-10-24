<?php

namespace App\Http\Controllers\Student;

use App\Domain\Counselling\Booking;
use App\Http\Controllers\Controller;
use App\Models\AvailabilitySlot;
use Illuminate\Http\Request;
use InvalidArgumentException;

/** A student books a call in one of their counsellor's free times, and can cancel it up to a day before. */
class BookingController extends Controller
{
    public function show(Request $request, Booking $booking)
    {
        $student = $request->user();
        $mine = AvailabilitySlot::where('booked_by', $student->id)->where('starts_at', '>', now()->subHour())->with('counsellor')->orderBy('starts_at')->get();
        $free = $booking->freeFor($student);

        return view('student.booking', ['mine' => $mine, 'days' => $free->groupBy(fn ($s) => $s->starts_at->copy()->timezone($student->timezone ?: 'UTC')->format('Y-m-d')), 'tz' => $student->timezone ?: 'UTC', 'counsellor' => $student->counsellor]);
    }

    public function store(Request $request, Booking $booking)
    {
        $data = $request->validate(['slot' => ['required', 'integer'], 'note' => ['nullable', 'string', 'max:500']]);
        try {
            $booking->book(AvailabilitySlot::findOrFail($data['slot']), $request->user(), $data['note'] ?? null);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('student.book')->with('status', 'Your call is booked. Your counsellor has been told.');
    }

    public function destroy(Request $request, AvailabilitySlot $slot, Booking $booking)
    {
        try {
            $booking->cancel($slot, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('student.book')->with('status', 'The call was cancelled.');
    }
}
