<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shareholder extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'legacy_id',
        'name',
        'phone',
        'address',
    ];

    public function bills(): HasMany
    {
        return $this->hasMany(ShareholderBill::class);
    }
}
