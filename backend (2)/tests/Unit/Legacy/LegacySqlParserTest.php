<?php

namespace Tests\Unit\Legacy;

use App\Services\Legacy\LegacyMonthMapper;
use App\Services\Legacy\LegacySqlParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LegacySqlParserTest extends TestCase
{
    #[Test]
    public function test_it_parses_insert_rows_with_quoted_strings_and_hex_blobs(): void
    {
        $sql = <<<'SQL'
INSERT INTO `tenants` (`ClientID`, `HouseID`, `ClientName`, `phone`, `Gender`, `Email`, `apartmentNo`, `service_amount`, `Passport`, `PassImage`, `Deposit`, `nextname`, `anddress`, `npassid`, `nphone`, `starteddate`, `status`, `username`) VALUES
(15, 3, 'Asho Mohamud Dulane', '254798893199', 'Female', 'N/A', '16 ', 10000, 'P 01305163', 0x616374697665, 85000, '0', '0', '0', '0', '2025-07-07', 'inactive', 'Mohamed Gelle');
SQL;

        $tables = (new LegacySqlParser)->parse($sql);

        $this->assertArrayHasKey('tenants', $tables);
        $this->assertCount(1, $tables['tenants']);
        $this->assertSame(15, $tables['tenants'][0]['ClientID']);
        $this->assertSame('16 ', $tables['tenants'][0]['apartmentNo']);
        $this->assertSame('active', $tables['tenants'][0]['PassImage']);
        $this->assertSame('inactive', $tables['tenants'][0]['status']);
    }

    #[Test]
    public function test_month_mapper_converts_names_and_rejects_invalid_values(): void
    {
        $mapper = new LegacyMonthMapper;

        $this->assertSame(7, $mapper->toBillingMonth('July'));
        $this->assertSame(['month' => 8, 'year' => 2025], $mapper->fromMonthYear('August', '2025'));
        $this->assertNull($mapper->fromMonthYear('Select Month', 2025));
        $this->assertSame(['month' => 6, 'year' => 2025], $mapper->fromTimestamp('2025-06-16 05:53:50'));
    }
}
