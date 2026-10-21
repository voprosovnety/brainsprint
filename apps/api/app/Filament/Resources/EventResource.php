<?php

namespace App\Filament\Resources;

use App\Models\Event;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class EventResource extends Resource
{
    protected static ?string $model = Event::class;

    protected static ?string $slug = 'events';

    protected static ?string $navigationLabel = 'Events';

    protected static ?string $modelLabel = 'Event';

    protected static ?string $pluralModelLabel = 'Events';

    public static function resolveRecordRouteBinding(int|string $key, ?\Closure $modifyQuery = null): ?Model
    {
        if (filter_var($key, FILTER_VALIDATE_INT) === false || (int) $key < 1) {
            return null;
        }

        return parent::resolveRecordRouteBinding($key, $modifyQuery);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable(),
            TextColumn::make('step_id')->searchable(),
            TextColumn::make('is_synthetic')->label('Dataset')->formatStateUsing(fn ($state) => $state ? 'Synthetic' : 'Real')->badge(),
            TextColumn::make('created_at')->dateTime(),
        ])->defaultSort('created_at', 'desc')
            ->filters([TernaryFilter::make('is_synthetic')->label('Synthetic data')])
            ->recordActions([ViewAction::make()])->toolbarActions([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('id'),
            TextEntry::make('run_id'),
            TextEntry::make('visitor_id'),
            TextEntry::make('name'),
            TextEntry::make('step_id'),
            TextEntry::make('funnel_version_id'),
            TextEntry::make('is_synthetic')->label('Dataset')->formatStateUsing(fn ($state) => $state ? 'Synthetic' : 'Real'),
            TextEntry::make('created_at'),
            ViewEntry::make('attribution')->view('filament.entries.json')->state(fn ($record) => json_encode($record->attribution, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))->columnSpanFull(),
            ViewEntry::make('properties')->view('filament.entries.json')->state(fn ($record) => json_encode($record->properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))->columnSpanFull(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => EventResource\Pages\ListRecords::route('/'),
            'view' => EventResource\Pages\ViewRecord::route('/{record}'),
        ];
    }
}
