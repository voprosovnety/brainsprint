<x-filament-panels::page>
    <x-filament::section heading="Observed anonymous visitors">
        <p>Derived from recorded events and runs. Pseudonyms are not login accounts or active-session counts. Visitors without recorded events are absent. No Laravel session data is shown.</p>
        <div role="region" tabindex="0" aria-label="Scrollable data table" style="overflow-x:auto;margin-top:1rem"><table class="bs-table"><thead><tr><th>Visitor pseudonym</th><th>Dataset</th><th>Events</th><th>Last observed (UTC)</th><th>Run state</th></tr></thead><tbody>
        @forelse($visitors as $v)<tr><td title="{{ $v->visitor_id }}">{{ \Illuminate\Support\Str::limit($v->visitor_id, 16) }}</td><td>{{ $v->is_synthetic ? 'Synthetic' : 'Real' }}</td><td>{{ $v->events }}</td><td>{{ $v->last_seen }}</td><td>@if($v->run_id)<a href="{{ \App\Filament\Resources\FunnelRunResource::getUrl('view', ['record' => $v->run_id]) }}">{{ $v->current_step }}</a>@else No run @endif</td></tr>@empty<tr><td colspan="5">No visitors with recorded events yet.</td></tr>@endforelse
        </tbody></table></div>
        <div style="margin-top:1rem">{{ $visitors->links() }}</div>
    </x-filament::section>
    @include('filament.pages.table-style')
</x-filament-panels::page>
