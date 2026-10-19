<?php

namespace App\Filament\Resources\FunnelVersionResource\Pages;

use App\Filament\Resources\FunnelVersionResource;

class ListRecords extends \Filament\Resources\Pages\ListRecords
{
    protected static string $resource = FunnelVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
