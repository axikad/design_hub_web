<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RolePermission extends Model
{
    protected $fillable = [
        'role_id',
        'permission_id',
        'read',
        'create',
        'update',
        'delete',
    ];

    protected function casts(): array
    {
        return [
            'read' => 'boolean',
            'create' => 'boolean',
            'update' => 'boolean',
            'delete' => 'boolean',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }
}
