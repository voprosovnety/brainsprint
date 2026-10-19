<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FunnelVersion extends Model
{
    protected $table = 'funnel_versions';

    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['configuration' => 'array', 'published_at' => 'datetime'];
    }
}
