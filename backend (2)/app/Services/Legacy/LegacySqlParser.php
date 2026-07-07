<?php

namespace App\Services\Legacy;

use RuntimeException;

class LegacySqlParser
{
    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public function parseFile(string $path): array
    {
        if (! is_readable($path)) {
            throw new RuntimeException("SQL dump not readable: {$path}");
        }

        return $this->parse(file_get_contents($path));
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public function parse(string $sql): array
    {
        $tables = [];
        $pattern = '/INSERT\s+INTO\s+`(\w+)`\s*\(([^)]+)\)\s*VALUES\s*/i';
        $offset = 0;

        while (preg_match($pattern, $sql, $match, PREG_OFFSET_CAPTURE, $offset)) {
            $table = $match[1][0];
            $columns = array_map(
                static fn (string $column): string => trim($column, " \t\n\r\0\x0B`"),
                explode(',', $match[2][0]),
            );
            $valuesStart = $match[0][1] + strlen($match[0][0]);
            $valuesSql = $this->extractValuesBlock($sql, $valuesStart);

            foreach ($this->parseRowStrings($valuesSql) as $rowString) {
                $values = $this->parseRowValues($rowString);
                $row = [];
                foreach ($columns as $index => $column) {
                    $row[$column] = $values[$index] ?? null;
                }
                $tables[$table][] = $row;
            }

            $offset = $valuesStart + strlen($valuesSql);
        }

        return $tables;
    }

    private function extractValuesBlock(string $sql, int $start): string
    {
        $length = strlen($sql);
        $depth = 0;
        $inQuote = false;
        $escaped = false;

        for ($i = $start; $i < $length; $i++) {
            $char = $sql[$i];

            if ($inQuote) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === "'") {
                    $inQuote = false;
                }

                continue;
            }

            if ($char === "'") {
                $inQuote = true;

                continue;
            }

            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
            } elseif ($char === ';' && $depth === 0) {
                return substr($sql, $start, $i - $start);
            }
        }

        return substr($sql, $start);
    }

    /**
     * @return list<string>
     */
    private function parseRowStrings(string $valuesSql): array
    {
        $rows = [];
        $length = strlen($valuesSql);
        $index = 0;

        while ($index < $length) {
            while ($index < $length && in_array($valuesSql[$index], [" ", "\n", "\r", "\t", ','], true)) {
                $index++;
            }

            if ($index >= $length || $valuesSql[$index] !== '(') {
                break;
            }

            $depth = 0;
            $inQuote = false;
            $escaped = false;
            $rowStart = $index;

            while ($index < $length) {
                $char = $valuesSql[$index];

                if ($inQuote) {
                    if ($escaped) {
                        $escaped = false;
                    } elseif ($char === '\\') {
                        $escaped = true;
                    } elseif ($char === "'") {
                        $inQuote = false;
                    }
                } else {
                    if ($char === "'") {
                        $inQuote = true;
                    } elseif ($char === '(') {
                        $depth++;
                    } elseif ($char === ')') {
                        $depth--;
                        if ($depth === 0) {
                            $rows[] = substr($valuesSql, $rowStart + 1, $index - $rowStart - 1);
                            $index++;

                            break;
                        }
                    }
                }

                $index++;
            }
        }

        return $rows;
    }

    /**
     * @return list<mixed>
     */
    private function parseRowValues(string $row): array
    {
        $tokens = $this->splitValueTokens($row);

        return array_map(fn (string $token): mixed => $this->castToken($token), $tokens);
    }

    /**
     * @return list<string>
     */
    private function splitValueTokens(string $row): array
    {
        $tokens = [];
        $length = strlen($row);
        $index = 0;
        $buffer = '';
        $inQuote = false;
        $escaped = false;

        while ($index < $length) {
            $char = $row[$index];

            if ($inQuote) {
                $buffer .= $char;
                if ($escaped) {
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === "'") {
                    $inQuote = false;
                }

                $index++;

                continue;
            }

            if ($char === "'") {
                $inQuote = true;
                $buffer .= $char;
                $index++;

                continue;
            }

            if ($char === ',') {
                $tokens[] = trim($buffer);
                $buffer = '';
                $index++;

                continue;
            }

            $buffer .= $char;
            $index++;
        }

        if ($buffer !== '') {
            $tokens[] = trim($buffer);
        }

        return $tokens;
    }

    private function castToken(string $token): mixed
    {
        if ($token === '' || strcasecmp($token, 'NULL') === 0) {
            return null;
        }

        if (str_starts_with($token, '0x') || str_starts_with($token, '0X')) {
            $hex = substr($token, 2);
            if ($hex === '') {
                return '';
            }

            return hex2bin($hex) ?: $token;
        }

        if (str_starts_with($token, "'") && str_ends_with($token, "'")) {
            $inner = substr($token, 1, -1);

            return str_replace(["\\'", '\\"', '\\\\'], ["'", '"', '\\'], $inner);
        }

        if (is_numeric($token)) {
            return str_contains($token, '.') ? (float) $token : (int) $token;
        }

        return $token;
    }
}
