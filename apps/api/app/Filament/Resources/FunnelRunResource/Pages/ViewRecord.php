<?php

namespace App\Filament\Resources\FunnelRunResource\Pages;

use App\Filament\Resources\FunnelRunResource;

class ViewRecord extends \Filament\Resources\Pages\ViewRecord
{
    protected static string $resource = FunnelRunResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
