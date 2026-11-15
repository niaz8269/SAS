<?php

namespace Tests\Feature\Editorial;

use App\Models\Audit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsScholarships;
use Tests\TestCase;

class ApplyFundingTypesTest extends TestCase
{
    use BuildsScholarships;
    use RefreshDatabase;

    private function file(array $entries): string
    {
        $path = tempnam(sys_get_temp_dir(), 'fund');
        file_put_contents($path, json_encode($entries));

        return $path;
    }

    public function test_it_sets_type_and_verification_with_an_audit_entry_and_is_repeatable(): void
    {
        $a = $this->publishedCycle(['external_key' => 'xx-a-2027', 'funding_type' => 'other']);
        $b = $this->publishedCycle(['external_key' => 'xx-b-2027', 'funding_type' => 'other']);
        $path = $this->file([
            ['external_key' => 'xx-a-2027', 'funding_type' => 'full', 'fully_funded_verified' => true, 'reason' => 'Tuition and living allowance are covered.'],
            ['external_key' => 'xx-b-2027', 'funding_type' => 'stipend_only', 'fully_funded_verified' => false, 'reason' => 'Monthly grant only.'],
            ['external_key' => 'xx-missing-2027', 'funding_type' => 'full', 'fully_funded_verified' => true, 'reason' => 'x'],
        ]);

        $this->artisan('sas:apply-funding-types', ['files' => [$path], '--dry-run' => true])->assertSuccessful();
        $this->assertSame('other', $a->fresh()->funding_type);

        $this->artisan('sas:apply-funding-types', ['files' => [$path]])->assertSuccessful();
        $this->assertSame('full', $a->fresh()->funding_type);
        $this->assertTrue($a->fresh()->fully_funded_verified);
        $this->assertSame('Fully funded', $a->fresh()->fundingLabel());
        $this->assertSame('stipend_only', $b->fresh()->funding_type);
        $this->assertSame(2, Audit::where('action', 'cycle.funding_type_set')->count());

        $this->artisan('sas:apply-funding-types', ['files' => [$path]])->assertSuccessful();
        $this->assertSame(2, Audit::where('action', 'cycle.funding_type_set')->count());
    }

    public function test_a_verified_flag_on_a_type_other_than_full_is_refused_and_a_reason_is_required(): void
    {
        $c = $this->publishedCycle(['external_key' => 'xx-c-2027', 'funding_type' => 'other']);
        $path = $this->file([
            ['external_key' => 'xx-c-2027', 'funding_type' => 'partial', 'fully_funded_verified' => true, 'reason' => 'Contradiction.'],
            ['external_key' => 'xx-c-2027', 'funding_type' => 'full', 'fully_funded_verified' => true],
        ]);

        $this->artisan('sas:apply-funding-types', ['files' => [$path]])->assertSuccessful();
        $this->assertSame('other', $c->fresh()->funding_type);
        $this->assertFalse((bool) $c->fresh()->fully_funded_verified);
    }
}
