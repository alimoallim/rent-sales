<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentReceiptSequence extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'module',
        'scope_id',
        'last_number',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope_id' => 'integer',
            'last_number' => 'integer',
        ];
    }
}
