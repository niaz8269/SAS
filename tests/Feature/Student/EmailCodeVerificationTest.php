<?php

namespace Tests\Feature\Student;

use App\Domain\Identity\EmailCodes;
use App\Enums\Role;
use App\Models\PartnerOrganisation;
use App\Models\User;
use App\Notifications\VerifyEmailCode;
use Database\Seeders\TaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EmailCodeVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TaxonomySeeder::class);
        $this->withoutMiddleware(ThrottleRequests::class);
        Notification::fake();
    }

    /** The code that was emailed to this person most recently. */
    private function sentCode(User $user): string
    {
        $code = null;
        Notification::assertSentTo($user, VerifyEmailCode::class, function (VerifyEmailCode $n) use (&$code) {
            $code = $n->code;

            return true;
        });

        return $code;
    }

    private function register(string $email = 'amina@example.test'): User
    {
        $this->post('/register', ['name' => 'Amina', 'email' => $email, 'password' => 'a-long-password-1', 'password_confirmation' => 'a-long-password-1', 'accept_privacy' => '1'])
            ->assertRedirect(route('verification.notice'));

        return User::where('email', $email)->firstOrFail();
    }

    public function test_a_new_student_gets_a_code_and_is_verified_by_typing_it(): void
    {
        $student = $this->register();
        $code = $this->sentCode($student);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->assertFalse($student->hasVerifiedEmail());
        $this->assertNotSame($code, DB::table('email_verification_codes')->where('user_id', $student->id)->value('code_hash'), 'only a hash is stored');

        $this->get(route('verification.notice'))->assertOk()->assertSee('6-digit code');
        $this->get('/student')->assertRedirect(route('verification.notice'));

        $this->post(route('verification.confirm'), ['code' => $code])->assertRedirect(route('student.form.show'));
        $this->assertTrue($student->fresh()->hasVerifiedEmail());
        $this->assertSame(0, DB::table('email_verification_codes')->count(), 'a code is single-use');
        $this->get('/student')->assertOk();
    }

    public function test_a_wrong_code_fails_and_five_wrong_tries_burn_the_code(): void
    {
        $student = $this->register();
        $code = $this->sentCode($student);
        $wrong = $code === '000000' ? '111111' : '000000';

        foreach (range(1, EmailCodes::MAX_ATTEMPTS) as $i) {
            $this->post(route('verification.confirm'), ['code' => $wrong])->assertSessionHasErrors('code');
        }
        $this->post(route('verification.confirm'), ['code' => $code])->assertSessionHasErrors('code');
        $this->assertFalse($student->fresh()->hasVerifiedEmail(), 'even the right code is refused after too many wrong tries');

        $this->travel(2)->minutes();
        $this->post(route('verification.send'))->assertSessionHas('status');
        $fresh = $this->sentCode($student);
        $this->post(route('verification.confirm'), ['code' => $fresh])->assertRedirect(route('student.form.show'));
    }

    public function test_a_code_expires_and_one_for_an_old_address_is_useless(): void
    {
        $student = $this->register();
        $code = $this->sentCode($student);

        $this->travel(EmailCodes::TTL_MINUTES + 1)->minutes();
        $this->post(route('verification.confirm'), ['code' => $code])->assertSessionHasErrors('code');
        $this->travelBack();

        $student->forceFill(['email' => 'other@example.test'])->save();
        auth()->forgetGuards(); // a real request starts from the database, not from the previous request's user
        $this->post(route('verification.confirm'), ['code' => $code])->assertSessionHasErrors('code');
        $this->assertFalse($student->fresh()->hasVerifiedEmail());
    }

    public function test_spaces_and_dashes_in_the_typed_code_are_ignored_but_letters_are_not_accepted(): void
    {
        $student = $this->register();
        $code = $this->sentCode($student);

        $this->post(route('verification.confirm'), ['code' => 'abcdef'])->assertSessionHasErrors('code');
        $this->post(route('verification.confirm'), ['code' => substr($code, 0, 3).' - '.substr($code, 3)])->assertRedirect(route('student.form.show'));
    }

    public function test_asking_again_straight_away_sends_nothing_new(): void
    {
        $student = $this->register();
        $this->post(route('verification.send'))->assertSessionHas('status', 'A code was sent a moment ago. Please wait a minute before asking for another.');
        Notification::assertSentToTimes($student, VerifyEmailCode::class, 1);

        $this->travel(61)->seconds();
        $this->post(route('verification.send'));
        Notification::assertSentToTimes($student, VerifyEmailCode::class, 2);
    }

    public function test_an_agent_added_by_staff_must_verify_with_a_code_at_first_sign_in(): void
    {
        $org = PartnerOrganisation::create(['name' => 'Example Agency']);
        $agent = User::factory()->unverified()->role(Role::PartnerManager)->create(['password' => 'a-long-test-password']);
        $agent->forceFill(['partner_organisation_id' => $org->id])->save();

        $this->post('/login', ['email' => $agent->email, 'password' => 'a-long-test-password']);
        $this->get(route('partner.home'))->assertRedirect(route('verification.notice'));
        $this->get(route('verification.notice'))->assertOk();
        $code = $this->sentCode($agent);

        $this->post(route('verification.confirm'), ['code' => $code])->assertRedirect(route('partner.home'));
        $this->get(route('partner.home'))->assertOk();
    }

    public function test_someone_else_cannot_use_a_code_sent_to_another_person(): void
    {
        $first = $this->register('first@example.test');
        $code = $this->sentCode($first);
        auth()->logout();

        $second = $this->register('second@example.test');
        $this->post(route('verification.confirm'), ['code' => $code])->assertSessionHasErrors('code');
        $this->assertFalse($second->fresh()->hasVerifiedEmail());
        $this->assertFalse($first->fresh()->hasVerifiedEmail());
    }

    public function test_a_verified_person_visiting_the_page_goes_home(): void
    {
        $this->actingAs(User::factory()->create())->get(route('verification.notice'))->assertRedirect(route('student.dashboard'));
    }
}
