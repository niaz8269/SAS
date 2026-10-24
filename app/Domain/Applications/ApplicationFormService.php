<?php

namespace App\Domain\Applications;

use App\Domain\Editorial\AuditLog;
use App\Models\ApplicationForm;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** Reads, validates and saves the master application form described in config/application_form.php. */
class ApplicationFormService
{
    /** @return array<string,array> */
    public function sections(): array
    {
        return config('application_form.sections');
    }

    public function section(string $key): array
    {
        return $this->sections()[$key] ?? abort(404);
    }

    /** The student's form, created on first use with what the profile already knows (never overwrites later). */
    public function formFor(User $user): ApplicationForm
    {
        $form = ApplicationForm::firstOrNew(['user_id' => $user->id]);
        if (! $form->exists) {
            $profile = $user->studentProfile;
            $parts = preg_split('/\s+/', trim($user->name), 2);
            $form->forceFill(['user_id' => $user->id, 'data' => array_filter([
                'given_name' => $parts[0] ?? null, 'surname' => $parts[1] ?? null,
                'email' => $user->email, 'phone' => $user->phone,
                'date_of_birth' => $profile?->date_of_birth?->format('Y-m-d'),
                'nationality' => $profile?->nationality?->name,
            ], fn ($v) => filled($v))])->save();
        }

        return $form;
    }

    /** Laravel validation rules for one section's submitted values. */
    public function rules(string $key): array
    {
        $section = $this->section($key);
        $rules = [];
        foreach ($section['fields'] as $name => $def) {
            $path = isset($section['repeat']) ? "rows.*.$name" : $name;
            $rules[$path] = $this->fieldRules($def);
        }
        if (isset($section['repeat'])) {
            $rules['rows'] = ['nullable', 'array', 'max:'.$section['repeat']];
        }

        return $rules;
    }

    private function fieldRules(array $def): array
    {
        return ['nullable', ...match ($def[1]) {
            'date' => ['date_format:Y-m-d'], // a whole date: day, month and year
            'email' => ['email', 'max:190'],
            'textarea' => ['string', 'max:600'],
            'select' => ['string', Rule::in($def['options'])],
            'yesno' => ['string', Rule::in(['yes', 'no'])],
            'country' => ['string', Rule::exists('countries', 'name')],
            default => ['string', 'max:190'],
        }];
    }

    /** Saves one section. Other sections are untouched; blank values are removed. */
    public function save(User $user, string $key, array $input): ApplicationForm
    {
        return $this->saveAll($user, [$key => $input]);
    }

    /**
     * Saves every section present in $sections (key => submitted values) in one go, with one audit entry. Sections not present are untouched;
     * within a present section, blank values are removed.
     *
     * @param  array<string,array>  $sections
     */
    public function saveAll(User $user, array $sections, ?User $actor = null): ApplicationForm
    {
        $form = $this->formFor($user);
        $data = $form->data ?? [];
        foreach ($sections as $key => $input) {
            $section = $this->section($key);
            $names = array_keys($section['fields']);
            if (isset($section['repeat'])) {
                $data[$key] = collect($input['rows'] ?? $input)->map(fn ($row) => array_filter(collect($row)->only($names)->all(), 'filled'))->filter()->values()->all();

                continue;
            }
            foreach ($names as $name) {
                $value = $input[$name] ?? null;
                filled($value) ? $data[$name] = $value : $this->forget($data, $name);
            }
        }
        $form->forceFill(['data' => $data])->save();
        AuditLog::record('application_form.saved', $form, null, ['sections' => array_keys($sections)], null, $actor ?? $user); // field values are never logged

        return $form;
    }

    public function declare(User $user, bool $agreed): ApplicationForm
    {
        $form = $this->formFor($user);
        $data = $form->data ?? [];
        if (filled($data['_declared_at'] ?? null) === $agreed) {
            return $form; // nothing changed
        }
        $agreed ? $data['_declared_at'] = now()->toDateTimeString() : $this->forget($data, '_declared_at');
        $form->forceFill(['data' => $data])->save();
        AuditLog::record($agreed ? 'application_form.declared' : 'application_form.declaration_withdrawn', $form, null, null, null, $user);

        return $form;
    }

    /**
     * The required answers of a section as [label, answered] pairs. Ordinary sections list their `required` fields. A repeating section
     * with `required_first` needs those fields in its first entry (so SAS gets at least two education records), and in any later entry
     * the student has started.
     *
     * @return list<array{0:string,1:bool}>
     */
    private function requiredCells(string $key, array $section, array $data): array
    {
        $cells = [];
        if (! isset($section['repeat'])) {
            foreach ($section['fields'] as $name => $def) {
                ($def['required'] ?? false) && $cells[] = [$def[0], filled($data[$name] ?? null)];
            }

            return $cells;
        }
        $required = $section['required_first'] ?? [];
        $rows = array_values((array) ($data[$key] ?? []));
        foreach (range(0, $section['repeat'] - 1) as $i) {
            $row = (array) ($rows[$i] ?? []);
            if ($required === [] || ($i > 0 && collect($row)->filter(fn ($v) => filled($v))->isEmpty())) {
                continue;
            }
            foreach ($required as $name) {
                $cells[] = [$section['title'].', entry '.($i + 1).': '.$section['fields'][$name][0], filled($row[$name] ?? null)];
            }
        }

        return $cells;
    }

    /** Labels of the required answers still missing, plus the declaration. Empty means complete. */
    public function missing(ApplicationForm $form): array
    {
        $data = $form->data ?? [];
        $missing = [];
        foreach ($this->sections() as $key => $section) {
            foreach ($this->requiredCells($key, $section, $data) as [$label, $answered]) {
                $answered || $missing[] = $label;
            }
        }
        if (blank($data['_declared_at'] ?? null)) {
            $missing[] = 'Declaration';
        }

        return $missing;
    }

    /** @return array<string,array{required:int,answered:int}> required answers per section, to show which sections still need work */
    public function sectionProgress(ApplicationForm $form): array
    {
        $data = $form->data ?? [];
        $out = [];
        foreach ($this->sections() as $key => $section) {
            $cells = collect($this->requiredCells($key, $section, $data));
            $out[$key] = ['required' => $cells->count(), 'answered' => $cells->filter(fn ($c) => $c[1])->count()];
        }

        return $out;
    }

    public function percent(ApplicationForm $form): int
    {
        $total = collect($this->sections())->sum(fn ($s, $key = null) => isset($s['repeat']) ? count($s['required_first'] ?? []) : collect($s['fields'])->filter(fn ($d) => $d['required'] ?? false)->count()) + 1;

        return max(0, (int) round(($total - count($this->missing($form))) / $total * 100));
    }

    /**
     * Problems with study dates that single-field rules cannot see: an end before the start, a start in the future, a date before 1940.
     *
     * @param  array<string,array>  $sections  the submitted sections, as in saveAll
     * @return array<string,string> error message by input path
     */
    public function periodErrors(array $sections): array
    {
        $errors = [];
        $check = function (string $path, ?string $from, ?string $to, array $what) use (&$errors) {
            try {
                $f = filled($from) ? Carbon::parse($from) : null;
                $t = filled($to) ? Carbon::parse($to) : null;
            } catch (\Throwable) {
                return;
            }
            if ($f && ($f->lt(Carbon::parse('1940-01-01')) || $f->isFuture())) {
                $errors[$path.'.'.$what[0]] = 'The start date must be between 1940 and today.';
            }
            if ($t && $t->lt(Carbon::parse('1940-01-01'))) {
                $errors[$path.'.'.$what[1]] = 'The end date is too early.';
            }
            if ($f && $t && $t->lt($f)) {
                $errors[$path.'.'.$what[1]] = 'The end date must be on or after the start date.';
            }
        };
        if (isset($sections['education'])) {
            $check('sections.education', $sections['education']['edu_from'] ?? null, $sections['education']['edu_to'] ?? null, ['edu_from', 'edu_to']);
        }
        foreach (['other_education', 'employment'] as $key) {
            foreach ((array) ($sections[$key]['rows'] ?? $sections[$key] ?? []) as $i => $row) {
                $check('sections.'.$key.'.rows.'.$i, $row['from'] ?? null, $row['to'] ?? null, ['from', 'to']);
            }
        }

        return $errors;
    }

    private function forget(array &$data, string $name): void
    {
        unset($data[$name]);
    }
}
