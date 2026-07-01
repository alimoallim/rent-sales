<?php

namespace Tests\Unit\Legacy;

use App\Services\Legacy\LegacyImporter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LegacyImportDryRunTest extends TestCase
{
    #[Test]
    public function test_dry_run_parses_production_dump_without_database_writes(): void
    {
        $dumpPath = '/home/ali/legacy-app/rasulmar_karama.sql';

        if (! is_readable($dumpPath)) {
            $this->markTestSkipped('Legacy dump file not available.');
        }

        $report = app(LegacyImporter::class)->import($dumpPath, dryRun: true);

        $this->assertSame(142, $report->imported['tenants'] ?? 0);
        $this->assertSame(1283, $report->imported['rent_charges'] ?? 0);
        $this->assertSame(1135, $report->imported['tenant_water_bills'] ?? 0);
        $this->assertSame(55, $report->skipped['rent_charges'] ?? 0);
    }
}
