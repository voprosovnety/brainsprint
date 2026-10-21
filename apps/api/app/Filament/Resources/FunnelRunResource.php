<?php

namespace App\Filament\Resources;

use App\Models\FunnelRun;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class FunnelRunResource extends Resource
{
    protected static ?string $model = FunnelRun::class;

    protected static ?string $slug = 'runs';

    protected static ?string $navigationLabel = 'Runs';

    protected static ?string $modelLabel = 'Run';

    protected static ?string $pluralModelLabel = 'Runs';

    public static function resolveRecordRouteBinding(int|string $key, ?\Closure $modifyQuery = null): ?Model
    {
        if (! Str::isUuid($key)) {
            return null;
        }

        return parent::resolveRecordRouteBinding($key, $modifyQuery);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('id')->searchable()->limit(12)->tooltip(fn ($record) => $record->id),
            TextColumn::make('current_step')->searchable(),
            TextColumn::make('is_synthetic')->label('Dataset')->formatStateUsing(fn ($state) => $state ? 'Synthetic' : 'Real')->badge(),
            TextColumn::make('created_at')->dateTime(),
            TextColumn::make('completed_at')->dateTime(),
        ])->defaultSort('created_at', 'desc')
            ->filters([TernaryFilter::make('is_synthetic')->label('Synthetic data')])
            ->recordActions([ViewAction::make()])->toolbarActions([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('id'),
            TextEntry::make('visitor_id'),
            TextEntry::make('funnel_version_id'),
            TextEntry::make('current_step'),
            TextEntry::make('is_synthetic')->label('Dataset')->formatStateUsing(fn ($state) => $state ? 'Synthetic' : 'Real'),
            TextEntry::make('created_at'),
            TextEntry::make('completed_at'),
            ViewEntry::make('answers')->view('filament.entries.json')->state(fn ($record) => json_encode($record->answers, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))->columnSpanFull(),
            ViewEntry::make('result')->view('filament.entries.json')->state(fn ($record) => json_encode($record->result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))->columnSpanFull(),
            ViewEntry::make('attribution')->view('filament.entries.json')->state(fn ($record) => json_encode($record->attribution, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))->columnSpanFull(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => FunnelRunResource\Pages\ListRecords::route('/'),
            'view' => FunnelRunResource\Pages\ViewRecord::route('/{record}'),
        ];
    }
}
