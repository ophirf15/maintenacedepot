<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'group', 'key', 'value', 'value_type', 'is_encrypted', 'is_public', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_encrypted' => 'boolean',
            'is_public' => 'boolean',
        ];
    }
}
