<?php

namespace App\Services\Legacy;

class LegacyImportReport
{
    /** @var array<string, int> */
    public array $imported = [];

    /** @var array<string, int> */
    public array $skipped = [];

    /** @var list<string> */
    public array $warnings = [];

    public function increment(string $entity, int $by = 1): void
    {
        $this->imported[$entity] = ($this->imported[$entity] ?? 0) + $by;
    }

    public function skip(string $entity, int $by = 1): void
    {
        $this->skipped[$entity] = ($this->skipped[$entity] ?? 0) + $by;
    }

    public function warn(string $message): void
    {
        $this->warnings[] = $message;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'imported' => $this->imported,
            'skipped' => $this->skipped,
            'warnings' => $this->warnings,
        ];
    }
}
