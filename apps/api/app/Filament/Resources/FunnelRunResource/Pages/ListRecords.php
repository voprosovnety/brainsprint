<?php

namespace App\Filament\Resources\FunnelRunResource\Pages;

use App\Filament\Resources\FunnelRunResource;

class ListRecords extends \Filament\Resources\Pages\ListRecords
{
    protected static string $resource = FunnelRunResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
