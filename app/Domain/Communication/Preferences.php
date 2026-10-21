<?php

namespace App\Domain\Communication;

use App\Models\NotificationPreference;
use App\Models\User;

/** NOT-02: per-category / per-channel preferences with recorded consent. */
class Preferences
{
    public const CATEGORIES = [
        'deadline_reminders' => 'Deadline reminders for saved and planned scholarships',
        'transactional' => 'Account and enquiry messages',
        'marketing' => 'News and offers',
    ];

    public const CHANNELS = ['in_app' => 'In-app', 'mail' => 'Email'];

    /** Defaults are visible and editable. Optional categories are off until the student opts in. */
    public static function defaults(): array
    {
        return [
            'deadline_reminders' => ['in_app' => true, 'mail' => false],
            'transactional' => ['in_app' => true, 'mail' => true],
            'marketing' => ['in_app' => false, 'mail' => false],
        ];
    }

    public static function ensureDefaults(User $user): void
    {
        foreach (self::defaults() as $category => $channels) {
            foreach ($channels as $channel => $enabled) {
                NotificationPreference::firstOrCreate(
                    ['user_id' => $user->id, 'category' => $category, 'channel' => $channel],
                    ['enabled' => $enabled, 'source' => 'default', 'consented_at' => $enabled ? now() : null,
                        'consent_text' => $enabled ? 'Default setting at registration' : null],
                );
            }
        }
    }

    public static function set(User $user, string $category, string $channel, bool $enabled, string $source = 'preferences_page'): void
    {
        abort_unless(isset(self::CATEGORIES[$category], self::CHANNELS[$channel]), 422);
        $pref = NotificationPreference::firstOrNew(['user_id' => $user->id, 'category' => $category, 'channel' => $channel]);
        $pref->enabled = $enabled;
        $pref->source = $source;
        $pref->consented_at = now();
        $pref->consent_text = ($enabled ? 'Opted in to ' : 'Opted out of ').strtolower(self::CATEGORIES[$category]).' by '.strtolower(self::CHANNELS[$channel]);
        $pref->save();
    }

    public static function enabled(User $user, string $category, string $channel): bool
    {
        return NotificationPreference::where(['user_id' => $user->id, 'category' => $category, 'channel' => $channel])->value('enabled') === true
            || NotificationPreference::where(['user_id' => $user->id, 'category' => $category, 'channel' => $channel])->value('enabled') === 1;
    }
}
