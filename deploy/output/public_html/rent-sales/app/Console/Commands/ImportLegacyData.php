<?php

namespace App\Console\Commands;

use App\Services\Legacy\LegacyImporter;
use Illuminate\Console\Command;
use Throwable;

class ImportLegacyData extends Command
{
    protected $signature = 'legacy:import
                            {file : Path to the legacy MySQL SQL dump}
                            {--dry-run : Parse and validate without writing to the database}
                            {--fresh : Truncate domain tables before import (keeps users)}
                            {--force : Skip confirmation when using --fresh}
                            {--skip-sales : Import rental domain only}';

    protected $description = 'Import legacy MySQL dump data into the PostgreSQL schema';

    public function handle(LegacyImporter $importer): int
    {
        $path = $this->argument('file');
        $dryRun = (bool) $this->option('dry-run');
        $fresh = (bool) $this->option('fresh');
        $skipSales = (bool) $this->option('skip-sales');

        if (! is_readable($path)) {
            $this->error("File not readable: {$path}");

            return self::FAILURE;
        }

        if ($fresh && $dryRun) {
            $this->warn('--fresh is ignored during --dry-run.');
        }

        if ($fresh && ! $dryRun && ! $this->option('force') && ! $this->confirm('This will delete all rental/sales domain data. Continue?')) {
            $this->info('Import cancelled.');

            return self::SUCCESS;
        }

        $this->info($dryRun ? 'Dry-run: validating legacy dump...' : 'Importing legacy dump...');

        try {
            $report = $importer->import($path, $dryRun, $fresh, $skipSales);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info($dryRun ? 'Dry-run summary' : 'Import complete');

        $rows = collect($report->imported)
            ->sortKeys()
            ->map(fn (int $count, string $entity): array => [$entity, (string) $count]);

        if ($rows->isNotEmpty()) {
            $this->table(['Entity', 'Rows'], $rows->all());
        }

        if ($report->skipped !== []) {
            $this->newLine();
            $this->warn('Skipped rows:');
            foreach ($report->skipped as $entity => $count) {
                $this->line("  {$entity}: {$count}");
            }
        }

        if ($report->warnings !== []) {
            $this->newLine();
            $this->warn('Warnings ('.count($report->warnings).'):');
            foreach (array_slice($report->warnings, 0, 20) as $warning) {
                $this->line("  - {$warning}");
            }

            if (count($report->warnings) > 20) {
                $this->line('  ... and '.(count($report->warnings) - 20).' more');
            }
        }

        return self::SUCCESS;
    }
}
