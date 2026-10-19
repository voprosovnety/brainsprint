<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventResource;

class ViewRecord extends \Filament\Resources\Pages\ViewRecord
{
    protected static string $resource = EventResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
