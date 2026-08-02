<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstallationState extends Model
{
    protected $table = 'installation_state';

    protected $fillable = [
        'instance_uuid', 'is_installed', 'current_step', 'completed_steps',
        'installed_version', 'installed_at', 'installed_by', 'install_token',
    ];

    protected function casts(): array
    {
        return [
            'is_installed' => 'boolean',
            'completed_steps' => 'array',
            'installed_at' => 'datetime',
        ];
    }
}
