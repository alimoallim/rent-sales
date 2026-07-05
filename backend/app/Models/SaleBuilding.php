<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleBuilding extends Model
{
    use LogsActivity;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'legacy_id',
        'name',
    ];

    public function units(): HasMany
    {
        return $this->hasMany(SaleUnit::class);
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalesPayment::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(SalesExpense::class);
    }
}
