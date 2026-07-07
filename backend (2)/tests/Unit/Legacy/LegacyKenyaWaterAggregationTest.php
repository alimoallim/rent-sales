<?php

namespace Tests\Unit\Legacy;

use App\Services\Legacy\LegacyImporter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LegacyKenyaWaterAggregationTest extends TestCase
{
    #[Test]
    public function test_dry_run_merges_multiple_kenya_water_rows_per_billing_period(): void
    {
        $dumpPath = '/home/ali/legacy-app/rasulmar_karama.sql';

        if (! is_readable($dumpPath)) {
            $this->markTestSkipped('Legacy dump file not available.');
        }

        $report = app(LegacyImporter::class)->import($dumpPath, dryRun: true);

        $this->assertSame(12, $report->imported['building_water_utility_bills'] ?? 0);
        $this->assertSame(1, $report->skipped['building_water_utility_bills'] ?? 0);
    }
}
