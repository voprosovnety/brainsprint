<?php

namespace App\Filament\Resources\FunnelVersionResource\Pages;

use App\Filament\Resources\FunnelVersionResource;

class ViewRecord extends \Filament\Resources\Pages\ViewRecord
{
    protected static string $resource = FunnelVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
