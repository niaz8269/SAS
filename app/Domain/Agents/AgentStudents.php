<?php

namespace App\Domain\Agents;

use App\Domain\Applications\ApplicationFormService;
use App\Domain\Editorial\AuditLog;
use App\Models\Country;
use App\Models\PartnerOrganisation;
use App\Models\User;
use App\Notifications\StudentInvited;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * A recruiting agent adds students they look after. The student gets an account with a random password nobody knows and an email
 * invitation to choose their own; until then only the agent can work on the student's applications. Every addition is audited
 * with the agent's confirmation that the student agreed.
 */
class AgentStudents
{
    public const CONSENT = 'I confirm this student asked me to apply for them and agreed that SAS processes their personal data for their applications.';

    public function __construct(private ApplicationFormService $forms) {}

    /** @return array<string,array> validation rules for one student's basic details */
    public function rules(): array
    {
        return [
            'surname' => ['required', 'string', 'max:80'], 'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'country' => ['required', 'string', 'exists:countries,name'],
            'passport_number' => ['nullable', 'string', 'max:40'],
            'date_of_birth' => ['required', 'date', 'before:today'],
        ];
    }

    /** @param array{surname:string,name:string,email:string,country:string,passport_number?:?string,date_of_birth:string} $data */
    public function add(PartnerOrganisation $agent, User $actor, array $data, bool $invite = true): User
    {
        if (User::where('email', $data['email'])->exists()) {
            throw new InvalidArgumentException('Someone already has an account with the email '.$data['email'].'.');
        }
        $student = DB::transaction(function () use ($agent, $actor, $data) {
            $student = new User(['name' => trim($data['name'].' '.$data['surname']), 'email' => $data['email'], 'password' => Str::random(40)]);
            $student->forceFill(['managed_by_organisation_id' => $agent->id, 'status' => 'active'])->save();
            $this->forms->saveAll($student, [
                'personal' => ['surname' => $data['surname'], 'given_name' => $data['name'], 'nationality' => $data['country'], 'passport_no' => $data['passport_number'] ?? null, 'date_of_birth' => $data['date_of_birth']],
                'contact' => ['email' => $data['email']],
            ], $actor);
            AuditLog::record('agent.student_added', $student, null, ['agent' => $agent->id, 'consent' => self::CONSENT], null, $actor);

            return $student;
        });
        $invite && $this->invite($student, $agent);

        return $student;
    }

    /** Sends (or resends) the email that lets the student choose their own password. */
    public function invite(User $student, PartnerOrganisation $agent): void
    {
        $token = Password::broker('invites')->createToken($student);
        $student->notify(new StudentInvited($token, $agent));
    }

    /** Updates the basic details an agent keeps for a student who has not taken over their own account yet. */
    public function update(User $student, User $actor, array $data): User
    {
        if (User::where('email', $data['email'])->where('id', '!=', $student->id)->exists()) {
            throw new InvalidArgumentException('Someone already has an account with the email '.$data['email'].'.');
        }
        DB::transaction(function () use ($student, $actor, $data) {
            $student->forceFill(['name' => trim($data['name'].' '.$data['surname']), 'email' => $data['email']])->save();
            $this->forms->saveAll($student, [
                'personal' => array_merge(collect($this->forms->formFor($student)->data ?? [])->only(array_keys($this->forms->section('personal')['fields']))->all(), [
                    'surname' => $data['surname'], 'given_name' => $data['name'], 'nationality' => $data['country'], 'passport_no' => $data['passport_number'] ?? null, 'date_of_birth' => $data['date_of_birth'],
                ]),
            ], $actor);
            AuditLog::record('agent.student_updated', $student, null, null, null, $actor);
        });

        return $student;
    }

    /**
     * Adds students from CSV text with the header surname,name,email,country,passport_number,date_of_birth (YYYY-MM-DD).
     * Every valid row is added; each bad row is reported with its line number and nothing else is affected.
     *
     * @return array{added:int,skipped:array<int,string>}
     */
    public function importCsv(PartnerOrganisation $agent, User $actor, string $csv, bool $invite = true): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim(preg_replace('/^\xEF\xBB\xBF/', '', $csv)));
        $header = array_map(fn ($h) => Str::snake(trim($h)), str_getcsv(array_shift($lines) ?? ''));
        $required = ['surname', 'name', 'email', 'country', 'date_of_birth'];
        if (array_diff($required, $header)) {
            throw new InvalidArgumentException('The first line must name the columns: surname, name, email, country, passport_number, date_of_birth.');
        }
        if (count($lines) > 200) {
            throw new InvalidArgumentException('Please import at most 200 students at a time.');
        }
        $report = ['added' => 0, 'skipped' => []];
        foreach ($lines as $i => $line) {
            if (trim($line) === '') {
                continue;
            }
            $number = $i + 2;
            $cells = str_getcsv($line);
            $row = array_combine($header, array_pad(array_slice($cells, 0, count($header)), count($header), null));
            $row = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $row);
            $check = Validator::make($row, $this->rules());
            if ($check->fails()) {
                $report['skipped'][$number] = $check->errors()->first();

                continue;
            }
            try {
                $this->add($agent, $actor, $check->validated(), $invite);
                $report['added']++;
            } catch (InvalidArgumentException $e) {
                $report['skipped'][$number] = $e->getMessage();
            }
        }

        return $report;
    }

    /** @return list<string> country names a student can be a citizen of */
    public function countries(): array
    {
        return Country::orderBy('name')->pluck('name')->all();
    }
}
