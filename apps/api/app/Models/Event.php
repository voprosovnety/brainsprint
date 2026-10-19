<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $table = 'events';

    public $timestamps = false;

    public $incrementing = true;

    protected $keyType = 'int';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['attribution' => 'array', 'properties' => 'array', 'is_synthetic' => 'boolean', 'created_at' => 'datetime'];
    }
}
