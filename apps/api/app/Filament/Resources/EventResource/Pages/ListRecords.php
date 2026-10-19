<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventResource;

class ListRecords extends \Filament\Resources\Pages\ListRecords
{
    protected static string $resource = EventResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
