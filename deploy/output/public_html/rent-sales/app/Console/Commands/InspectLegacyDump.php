<?php

namespace App\Console\Commands;

use App\Services\Legacy\LegacySqlParser;
use Illuminate\Console\Command;

class InspectLegacyDump extends Command
{
    protected $signature = 'legacy:inspect {file : Path to the legacy MySQL SQL dump}';

    protected $description = 'Show which legacy tables and row counts are available in a SQL dump';

    public function handle(LegacySqlParser $parser): int
    {
        $path = $this->argument('file');

        if (! is_readable($path)) {
            $this->error("File not readable: {$path}");

            return self::FAILURE;
        }

        $data = $parser->parseFile($path);
        ksort($data);

        $rows = collect($data)->map(fn (array $tableRows, string $table): array => [
            $table,
            (string) count($tableRows),
        ]);

        if ($rows->isEmpty()) {
            $this->warn('No INSERT statements found. Export the database again with data included.');

            return self::FAILURE;
        }

        $this->info('Legacy dump tables:');
        $this->table(['Table', 'Rows'], $rows->values()->all());

        $expected = [
            'categories' => 'rental buildings',
            'houses' => 'rental units',
            'tenants' => 'tenants',
            'charge' => 'rent charges',
            'payments' => 'rent payments',
            'water_bill' => 'tenant water bills',
            'electricity' => 'building electricity',
            'kenya_water' => 'Nairobi water utilities',
            'buildings' => 'sale buildings',
            'clients' => 'sale clients',
            'cpayments' => 'sale payments',
        ];

        $this->newLine();
        $this->info('Import readiness:');

        foreach ($expected as $table => $label) {
            $count = count($data[$table] ?? []);
            $status = $count > 0 ? "<fg=green>{$count} rows</>" : '<fg=yellow>missing</>';
            $this->line("  {$label} ({$table}): {$status}");
        }

        $tenantCount = count($data['tenants'] ?? []);
        if ($tenantCount === 0) {
            $this->newLine();
            $this->warn('No tenant rows found. A structure-only export cannot populate balances or history.');
            $this->line('In phpMyAdmin: Export → Custom → select all tables → check "Add INSERT statements".');
        }

        return self::SUCCESS;
    }
}
