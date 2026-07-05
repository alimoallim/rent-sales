<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shareholder extends Model
{
    use LogsActivity;
    use SoftDeletes;

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
