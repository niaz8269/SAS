<?php

namespace Tests\Feature\Counselling;

use App\Domain\Counselling\Booking;
use App\Domain\Counselling\Counselling;
use App\Domain\Counselling\Timeline;
use App\Enums\Role;
use App\Filament\Resources\AvailabilitySlots\AvailabilitySlotResource;
use App\Filament\Resources\AvailabilitySlots\Pages\ManageAvailabilitySlots;
use App\Filament\Resources\Students\Pages\ViewStudent;
use App\Models\AvailabilitySlot;
use App\Models\Consultation;
use App\Models\User;
use App\Notifications\CallBooked;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/** The call and meeting log, and students booking free times of their counsellor. */
class CallsAndBookingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function counsellor(string $name = 'Counsellor One'): User
    {
        return User::factory()->role(Role::Advisor)->create(['name' => $name, 'timezone' => 'Asia/Karachi']);
    }

    public function test_a_status_update_can_log_how_the_counsellor_was_in_touch_and_the_next_step(): void
    {
        $counsellor = $this->counsellor();
        $amina = User::factory()->create(['name' => 'Amina Khan']);
        app(Counselling::class)->assign($amina, $counsellor);
        $this->actingAs($counsellor);

        Livewire::test(ViewStudent::class, ['record' => $amina->id])->callAction('updateStatus', ['status' => 'contacted', 'contact_type' => 'call', 'comment' => 'Spoke for 20 minutes.', 'next_step' => 'Send the shortlist on Friday'])->assertHasNoActionErrors();
        $entry = $amina->fresh()->latestFollowUp;
        $this->assertSame('call', $entry->contact_type);
        $this->assertSame('Send the shortlist on Friday', $entry->next_step);

        Livewire::test(ViewStudent::class, ['record' => $amina->id])->callAction('updateStatus', ['status' => 'contacted', 'contact_type' => 'carrier-pigeon', 'comment' => 'x'])->assertHasActionErrors(['contact_type']);
        $this->assertSame('call', $amina->fresh()->latestFollowUp->contact_type, 'an unknown kind of contact is refused');

        $timeline = app(Timeline::class)->for($amina)->firstWhere('kind', 'Status');
        $this->assertNotNull($timeline);
        Livewire::test(ViewStudent::class, ['record' => $amina->id])->assertSee('Send the shortlist on Friday')->assertDontSee('Next step: None');
    }

    public function test_a_counsellor_adds_free_times_in_their_own_time_zone_without_duplicates(): void
    {
        $counsellor = $this->counsellor();
        $this->actingAs($counsellor);
        $day = now()->addDays(3)->toDateString();

        Livewire::test(ManageAvailabilitySlots::class)->callAction('addSlots', ['date' => $day, 'from' => '09:00', 'to' => '11:00', 'minutes' => 30])->assertHasNoActionErrors();
        $slots = AvailabilitySlot::orderBy('starts_at')->get();
        $this->assertCount(4, $slots);
        $this->assertSame('04:00', $slots->first()->starts_at->format('H:i'), '09:00 in Karachi (UTC+5) is 04:00 UTC');
        Livewire::test(ManageAvailabilitySlots::class)->callAction('addSlots', ['date' => $day, 'from' => '09:00', 'to' => '10:00', 'minutes' => 30]);
        $this->assertSame(4, AvailabilitySlot::count(), 'the same times are not added twice');

        $this->expectException(\InvalidArgumentException::class);
        app(Booking::class)->addSlots($counsellor, $day, '12:00', '10:00', 30);
    }

    public function test_a_student_books_cancels_and_cannot_double_book(): void
    {
        Notification::fake();
        $counsellor = $this->counsellor();
        $other = $this->counsellor('Counsellor Two');
        $student = User::factory()->create(['name' => 'Amina Khan', 'timezone' => 'Asia/Karachi']);
        app(Counselling::class)->assign($student, $counsellor);
        $day = now()->addDays(4)->toDateString();
        app(Booking::class)->addSlots($counsellor, $day, '10:00', '11:00', 30);
        app(Booking::class)->addSlots($other, $day, '10:00', '11:00', 30);
        $mine = AvailabilitySlot::where('counsellor_id', $counsellor->id)->orderBy('starts_at')->get();

        $this->actingAs($student)->get(route('student.book'))->assertOk()->assertSee('Counsellor One')->assertSee('10:00')->assertDontSee('Counsellor Two');
        $this->post(route('student.book.store'), ['slot' => $mine[0]->id, 'note' => 'Which university?'])->assertRedirect(route('student.book'));
        $slot = $mine[0]->fresh();
        $this->assertSame($student->id, $slot->booked_by);
        $consultation = Consultation::findOrFail($slot->consultation_id);
        $this->assertSame('confirmed', $consultation->status);
        $this->assertSame($counsellor->id, $consultation->advisor_id);
        $this->assertTrue($consultation->confirmed_start_utc->equalTo($slot->starts_at));
        Notification::assertSentTo($counsellor, CallBooked::class);

        $this->post(route('student.book.store'), ['slot' => $mine[1]->id])->assertSessionHas('error'); // one call at a time
        $this->assertNull($mine[1]->fresh()->booked_by);
        $this->get(route('student.book'))->assertSee('Your booked call');

        $this->actingAs($counsellor)->get('/admin/my-calendar')->assertOk()->assertSee('Consultation')->assertSee('Amina Khan');

        $this->actingAs($student)->delete(route('student.book.destroy', $slot))->assertRedirect(route('student.book'));
        $this->assertNull($slot->fresh()->booked_by);
        $this->assertSame('cancelled', $consultation->fresh()->status);

        // Someone else's booking cannot be cancelled; a booking less than a day away cannot be cancelled at all.
        app(Booking::class)->book($mine[0]->fresh(), $student);
        $this->actingAs(User::factory()->create())->delete(route('student.book.destroy', $mine[0]))->assertSessionHas('error');
        $this->assertSame($student->id, $mine[0]->fresh()->booked_by);
        $soon = new AvailabilitySlot;
        $soon->forceFill(['counsellor_id' => $counsellor->id, 'starts_at' => now()->addHours(5), 'duration_minutes' => 30, 'booked_by' => $student->id])->save();
        $this->actingAs($student)->delete(route('student.book.destroy', $soon))->assertSessionHas('error');
    }

    public function test_slots_in_the_next_two_hours_or_already_taken_cannot_be_booked(): void
    {
        $counsellor = $this->counsellor();
        $student = User::factory()->create();
        $late = new AvailabilitySlot;
        $late->forceFill(['counsellor_id' => $counsellor->id, 'starts_at' => now()->addMinutes(90), 'duration_minutes' => 30])->save();
        $this->assertSame(0, AvailabilitySlot::free()->count());

        $ok = new AvailabilitySlot;
        $ok->forceFill(['counsellor_id' => $counsellor->id, 'starts_at' => now()->addDays(2), 'duration_minutes' => 30])->save();
        app(Booking::class)->book($ok, $student);
        $this->expectException(\InvalidArgumentException::class);
        app(Booking::class)->book($ok->fresh(), User::factory()->create()); // already taken
    }

    public function test_only_the_owner_can_remove_a_free_time_and_a_booked_one_stays(): void
    {
        $counsellor = $this->counsellor();
        $manager = User::factory()->role(Role::Administrator)->create();
        $booked = new AvailabilitySlot;
        $booked->forceFill(['counsellor_id' => $counsellor->id, 'starts_at' => now()->addDays(2), 'duration_minutes' => 30, 'booked_by' => User::factory()->create()->id])->save();
        $free = new AvailabilitySlot;
        $free->forceFill(['counsellor_id' => $counsellor->id, 'starts_at' => now()->addDays(3), 'duration_minutes' => 30])->save();

        $this->assertFalse(AvailabilitySlotResource::canDelete($booked));
        $this->actingAs($counsellor);
        $this->assertTrue(AvailabilitySlotResource::canDelete($free));
        $this->actingAs($this->counsellor('Counsellor Two'));
        $this->assertFalse(AvailabilitySlotResource::canDelete($free));
        $this->actingAs($manager)->get('/admin/availability-slots')->assertOk();
    }
}
