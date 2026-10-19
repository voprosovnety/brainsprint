<?php

namespace App\Filament\Resources;

use App\Models\FunnelVersion;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FunnelVersionResource extends Resource
{
    protected static ?string $model = FunnelVersion::class;

    protected static ?string $slug = 'configurations';

    protected static ?string $navigationLabel = 'Configurations';

    protected static ?string $modelLabel = 'Configuration';

    protected static ?string $pluralModelLabel = 'Configurations';

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('id')->searchable(),
            TextColumn::make('published_at')->dateTime(),
        ])->defaultSort('published_at', 'desc')

            ->recordActions([ViewAction::make()])->toolbarActions([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('id'),
            TextEntry::make('published_at'),
            ViewEntry::make('configuration')->view('filament.entries.json')->state(fn ($record) => json_encode($record->configuration, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))->columnSpanFull(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => FunnelVersionResource\Pages\ListRecords::route('/'),
            'view' => FunnelVersionResource\Pages\ViewRecord::route('/{record}'),
        ];
    }
}
