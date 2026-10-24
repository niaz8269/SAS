<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Staff now work in three roles. Everyone who held one of the retired roles keeps their access under the role that replaces it. */
    private const MERGE = [
        'service_manager' => 'administrator',
        'editor' => 'administrator',
        'reviewer' => 'administrator',
        'finance' => 'administrator',
        'specialist' => 'advisor',
    ];

    public function up(): void
    {
        foreach (self::MERGE as $old => $new) {
            foreach (DB::table('user_roles')->where('role', $old)->get() as $row) {
                if (DB::table('user_roles')->where('user_id', $row->user_id)->where('role', $new)->exists()) {
                    DB::table('user_roles')->where('id', $row->id)->delete();
                } else {
                    DB::table('user_roles')->where('id', $row->id)->update(['role' => $new, 'updated_at' => now()]);
                }
            }
        }
    }

    public function down(): void {} // the old roles are not restored
};
