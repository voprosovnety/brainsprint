<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FunnelRun extends Model
{
    protected $table = 'funnel_runs';

    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['answers' => 'array', 'result' => 'array', 'attribution' => 'array', 'is_synthetic' => 'boolean', 'created_at' => 'datetime', 'completed_at' => 'datetime'];
    }
}
