<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    private const SLUGS = [
        'chinese-government-scholarship-csc',
        'chinese-government-scholarship-bilateral-program-type-a',
        'chinese-government-scholarship-silk-road-program',
        'chinese-government-scholarship-university-program-type-b',
        'peking-university-scholarships-for-international-graduate-students-csc-pkus-bgs',
    ];

    public function up(): void
    {
        Schema::table('document_kinds', function (Blueprint $t) {
            $t->string('template_path')->nullable()->after('description'); // a blank form students download, fill in and upload here
        });

        // The blank SAS application form ships with the code; it is copied to private storage, where staff can replace it in the admin.
        $source = resource_path('document-templates/sas-application-form.pdf');
        if (File::exists($source)) {
            Storage::disk('local')->put('document-templates/sas-application-form.pdf', File::get($source));
        }

        // Optional for every scholarship, and mandatory where a rule says so (the CSC-based ones below).
        DB::table('document_kinds')->insertOrIgnore([
            'key' => 'csc_application_form', 'name' => 'SAS Application Form', 'description' => 'The SAS application form: download it blank or filled in with your answers, check it, sign it and upload the signed copy here.',
            'template_path' => 'document-templates/sas-application-form.pdf', 'default_requirement' => 'optional', 'sort' => 125, 'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $kind = DB::table('document_kinds')->where('key', 'csc_application_form')->value('id');
        foreach (DB::table('scholarship_programmes')->whereIn('slug', self::SLUGS)->pluck('id') as $programmeId) {
            DB::table('document_rules')->insertOrIgnore([
                'kind_id' => $kind, 'programme_id' => $programmeId, 'degree_level_id' => null, 'requirement' => 'required',
                'note' => 'Download the form (blank, or filled in with your answers), check it, sign it and upload the scan or PDF.',
                'source_url' => 'https://id.china-embassy.gov.cn/eng/whjy/lxxx/202510/t20251027_11741682.htm', 'checked_on' => '2026-10-24', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $kind = DB::table('document_kinds')->where('key', 'csc_application_form')->value('id');
        if ($kind) {
            DB::table('document_rules')->where('kind_id', $kind)->delete();
            DB::table('document_kinds')->where('id', $kind)->delete();
        }
        Schema::table('document_kinds', fn (Blueprint $t) => $t->dropColumn('template_path'));
    }
};
