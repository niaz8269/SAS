<?php

namespace App\Domain\Applications;

use App\Domain\Editorial\AuditLog;
use App\Models\ApplicationForm;
use App\Models\User;
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
            'date' => ['date'],
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

    /** Labels of the required answers still missing, plus the declaration. Empty means complete. */
    public function missing(ApplicationForm $form): array
    {
        $data = $form->data ?? [];
        $missing = [];
        foreach ($this->sections() as $section) {
            foreach ($section['fields'] as $name => $def) {
                if (($def['required'] ?? false) && blank($data[$name] ?? null)) {
                    $missing[] = $def[0];
                }
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
            $required = collect($section['fields'])->filter(fn ($d) => $d['required'] ?? false)->keys();
            $out[$key] = ['required' => $required->count(), 'answered' => $required->filter(fn ($n) => filled($data[$n] ?? null))->count()];
        }

        return $out;
    }

    public function percent(ApplicationForm $form): int
    {
        $total = collect($this->sections())->sum(fn ($s) => collect($s['fields'])->filter(fn ($d) => $d['required'] ?? false)->count()) + 1;

        return (int) round(($total - count($this->missing($form))) / $total * 100);
    }

    private function forget(array &$data, string $name): void
    {
        unset($data[$name]);
    }
}
