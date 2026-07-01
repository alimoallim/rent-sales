<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RentalBuilding extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'legacy_id',
        'name',
    ];

    public function units(): HasMany
    {
        return $this->hasMany(RentalUnit::class);
    }

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }
}
