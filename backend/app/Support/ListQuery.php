<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Http\Request;

class ListQuery
{
    public static function perPage(Request $request, int $default = 25, int $max = 100): int
    {
        return min(max($request->integer('per_page', $default), 1), $max);
    }

    /**
     * @param  array<int, string>  $columns
     * @param  array<string, string>  $relations
     */
    public static function applySearch(Builder $query, Request $request, array $columns = [], array $relations = []): void
    {
        $term = trim($request->string('search')->toString());

        if ($term === '' || ($columns === [] && $relations === [])) {
            return;
        }

        $pattern = self::prefixLikePattern($term);
        $grammar = $query->getGrammar();

        $query->where(function (Builder $builder) use ($columns, $relations, $pattern, $grammar): void {
            foreach ($columns as $column) {
                self::applyCaseInsensitiveColumnMatch($builder, $column, $pattern, $grammar);
            }

            foreach ($relations as $relation => $column) {
                $builder->orWhereHas(
                    $relation,
                    fn (Builder $relationQuery) => self::applyCaseInsensitiveColumnMatch(
                        $relationQuery,
                        $column,
                        $pattern,
                        $relationQuery->getGrammar(),
                        useOr: false,
                    ),
                );
            }
        });
    }

    private static function applyCaseInsensitiveColumnMatch(
        Builder $builder,
        string $column,
        string $pattern,
        Grammar $grammar,
        bool $useOr = true,
    ): void {
        $driver = $builder->getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            if ($useOr) {
                $builder->orWhere($column, 'ilike', $pattern);
            } else {
                $builder->where($column, 'ilike', $pattern);
            }

            return;
        }

        $wrapped = $grammar->wrap($column);
        $sql = 'LOWER(CAST('.$wrapped.' AS TEXT)) LIKE ?';

        if ($useOr) {
            $builder->orWhereRaw($sql, [$pattern]);
        } else {
            $builder->whereRaw($sql, [$pattern]);
        }
    }

    private static function prefixLikePattern(string $value): string
    {
        return self::escapeLike($value).'%';
    }

    private static function escapeLike(string $value): string
    {
        $normalized = mb_strtolower($value, 'UTF-8');

        return str_replace(['%', '_'], '', $normalized);
    }
}
