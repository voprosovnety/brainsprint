<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class Visitors extends Page
{
    protected string $view = 'filament.pages.visitors';

    protected static ?string $title = 'Anonymous visitors';

    public static function canAccess(): bool
    {
        return Analytics::canAccess();
    }

    protected function getViewData(): array
    {
        // Domain pseudonyms only. Never query the framework session tables or their payloads.
        $events = DB::table('events')->selectRaw('visitor_id, is_synthetic, count(*) as events, min(created_at) as first_seen, max(created_at) as last_seen')->whereNotNull('visitor_id')->groupBy('visitor_id', 'is_synthetic');
        $runs = DB::table('funnel_runs')->select('visitor_id', 'is_synthetic', 'id', 'current_step', 'created_at');

        return ['visitors' => DB::query()->fromSub($events, 'e')->leftJoinSub($runs, 'r', fn ($j) => $j->on('e.visitor_id', '=', 'r.visitor_id')->on('e.is_synthetic', '=', 'r.is_synthetic'))->select('e.*', 'r.id as run_id', 'r.current_step')->orderByDesc('e.last_seen')->paginate(25)];
    }
}
