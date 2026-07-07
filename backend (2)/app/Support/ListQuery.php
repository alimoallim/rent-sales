<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
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

        $pattern = '%'.$term.'%';

        $query->where(function (Builder $builder) use ($columns, $relations, $pattern): void {
            foreach ($columns as $column) {
                $builder->orWhere($column, 'ilike', $pattern);
            }

            foreach ($relations as $relation => $column) {
                $builder->orWhereHas($relation, fn (Builder $relationQuery) => $relationQuery->where($column, 'ilike', $pattern));
            }
        });
    }
}
