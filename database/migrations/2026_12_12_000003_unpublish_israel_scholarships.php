<?php

use App\Enums\PublicationState;
use App\Models\ScholarshipCycle;
use Illuminate\Database\Migrations\Migration;

/**
 * The owner asked on 13 November 2026 for every scholarship related to Israel to be removed from the site. The Israel Ministry of Foreign Affairs
 * scholarship is the only one whose funder, host country and title are Israeli. It is set back to draft rather than deleted: it leaves the pages,
 * the lists, the search and the sitemap at once, and nothing is lost (an editor can publish it again). Saving through the model clears the caches.
 */
return new class extends Migration
{
    private const KEYS = ['il-mfa-scholarship-2026'];

    public function up(): void
    {
        ScholarshipCycle::whereIn('external_key', self::KEYS)->get()->each(function (ScholarshipCycle $cycle) {
            $cycle->forceFill(['publication_state' => PublicationState::Draft])->save();
        });
    }

    public function down(): void
    {
        // Publishing it again is an editorial decision, so a rollback does not do it.
    }
};
