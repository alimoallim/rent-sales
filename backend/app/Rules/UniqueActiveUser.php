<?php

namespace App\Rules;

use Illuminate\Validation\Rule;

class UniqueActiveUser
{
    /**
     * @return \Illuminate\Validation\Rules\Unique
     */
    public static function column(string $column, ?int $ignoreUserId = null): \Illuminate\Validation\Rules\Unique
    {
        $rule = Rule::unique('users', $column)
            ->where(fn ($query) => $query->whereNull('deleted_at'));

        if ($ignoreUserId !== null) {
            $rule->ignore($ignoreUserId);
        }

        return $rule;
    }
}
