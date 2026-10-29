<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtpChallenge extends Model
{
    protected $fillable = [
        'phone_key',
        'flow',
        'name',
        'machine_ids',
        'code_hash',
        'attempts',
        'expires_at',
        'consumed_at',
        'last_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'machine_ids' => 'array',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'last_sent_at' => 'datetime',
        ];
    }
}
