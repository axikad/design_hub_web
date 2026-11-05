<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SettlementCycle extends Model
{
    protected $fillable = ['start_day', 'end_day', 'settlement_day', 'sort_order'];

    protected function casts(): array
    {
        return [
            'start_day' => 'integer',
            'end_day' => 'integer',
            'settlement_day' => 'integer',
            'sort_order' => 'integer',
        ];
    }
}
