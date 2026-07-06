<?php

namespace Tests\Feature\Legacy;

use App\Services\Legacy\LegacyBalanceValidator;
use App\Services\Legacy\LegacySqlParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LegacyBalanceValidatorTest extends TestCase
{
    private string $dumpPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dumpPath = '/home/ali/legacy-app/rasulmar_karama.sql';
    }

    #[Test]
    public function test_legacy_tenant_totals_match_dump_rows(): void
    {
        if (! is_readable($this->dumpPath)) {
            $this->markTestSkipped('Legacy dump file not available.');
        }

        $validator = new LegacyBalanceValidator(new LegacySqlParser);
        $data = $validator->parseDump($this->dumpPath);

        $sampleIds = $validator->sampleLegacyTenantIds($data, 3);
        $this->assertNotEmpty($sampleIds);

        foreach ($sampleIds as $legacyId) {
            $totals = $validator->legacyTenantTotals($data, $legacyId);
            $this->assertNotNull($totals);
            $this->assertMatchesRegularExpression('/^-?\d+\.\d{2}$/', $totals['balance']);
        }
    }

    #[Test]
    public function test_legacy_client_totals_match_dump_rows(): void
    {
        if (! is_readable($this->dumpPath)) {
            $this->markTestSkipped('Legacy dump file not available.');
        }

        $validator = new LegacyBalanceValidator(new LegacySqlParser);
        $data = $validator->parseDump($this->dumpPath);

        $sampleIds = $validator->sampleLegacyClientIds($data, 3);
        $this->assertNotEmpty($sampleIds);

        foreach ($sampleIds as $legacyId) {
            $totals = $validator->legacyClientTotals($data, $legacyId);
            $this->assertNotNull($totals);
            $this->assertMatchesRegularExpression('/^-?\d+\.\d{2}$/', $totals['balance']);
        }
    }
}
