<?php

namespace Tests\Feature\Legacy;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LegacyInspectTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_inspect_reports_empty_rasulmar_db_dump(): void
    {
        $dumpPath = '/home/ali/legacy-app/rasulmar_db.sql';

        if (! is_readable($dumpPath)) {
            $this->markTestSkipped('rasulmar_db.sql not available.');
        }

        $this->artisan('legacy:inspect', ['file' => $dumpPath])
            ->assertSuccessful()
            ->expectsOutputToContain('users')
            ->expectsOutputToContain('No tenant rows found');
    }
}
