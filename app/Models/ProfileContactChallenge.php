<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfileContactChallenge extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'target',
        'country_code',
        'mobile_number',
        'email',
        'code_hash',
        'attempts',
        'expires_at',
        'consumed_at',
        'last_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'last_sent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
