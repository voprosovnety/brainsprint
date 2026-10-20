<x-filament-panels::page>
    @include('filament.pages.table-style')
    <x-filament::section heading="Cohort and observation">
        <form wire:submit="apply" class="bs-grid">
            <label class="bs-field">From (UTC, inclusive)<input type="datetime-local" wire:model="from" required></label>
            <label class="bs-field">To (UTC, exclusive)<input type="datetime-local" wire:model="to" required></label>
            <label class="bs-field">Dataset<select wire:model="dataset"><option value="real">Real traffic</option><option value="synthetic">Synthetic · illustrative</option></select></label>
            <label class="bs-field">Observation hours<input type="number" min="1" max="168" wire:model="observationHours" required></label>
            <div><x-filament::button type="submit" wire:loading.attr="disabled">Apply cohort</x-filament::button></div>
        </form>
        @if($errors->any())<p role="alert" style="margin-top:1rem;color:#b42318">{{ $errors->first() }}</p>@endif
    </x-filament::section>
    @if($metrics)
    <p><strong>{{ $metrics['dataset'] === 'synthetic' ? 'Synthetic data — illustrative scenarios, not real traffic.' : 'Real traffic — synthetic rows excluded.' }}</strong> Outcomes observed through {{ $metrics['cohort']['as_of'] }}. Observation window: {{ $metrics['cohort']['observation_hours'] }} hours.</p>
    @if(!$metrics['totals']['runs'])<x-filament::section heading="No runs in this cohort">Choose another past range or select the synthetic dataset after running the demo seed.</x-filament::section>@endif
    <x-filament::section heading="Observed stages and adjacent conversion">
        <div role="region" tabindex="0" aria-label="Scrollable data table" style="overflow-x:auto"><table class="bs-table"><thead><tr><th>Stage</th><th>Unique runs</th><th>Conversion from previous stage</th></tr></thead><tbody>
        @foreach($metrics['totals'] as $stage => $count)<tr><td>{{ ucwords(str_replace('_',' ', $stage)) }}</td><td>{{ $count }}</td><td>@if(isset($metrics['stage_conversions'][$stage]))@php($c=$metrics['stage_conversions'][$stage]){{ $c['numerator'] }} / {{ $c['denominator'] }} · {{ $c['rate'] === null ? 'No eligible denominator' : round($c['rate']*100,1).'%' }}@else — @endif</td></tr>@endforeach
        </tbody></table></div>
        <p style="margin-top:1rem">Paywall reached means the no-payment demo boundary. Missing views are not invented. Adjacent conversion intersects observed stages.</p>
    </x-filament::section>
    <x-filament::section heading="V3 stages · separate cohort">
        <p>{{ $metrics['v3']['runs'] }} runs. Simulated unlocks are not purchases or revenue.</p>
        <div role="region" tabindex="0" aria-label="V3 stages" style="overflow-x:auto"><table class="bs-table"><thead><tr><th>Stage</th><th>Unique runs</th><th>Conversion from previous</th></tr></thead><tbody>
        @foreach($metrics['v3']['totals'] as $stage => $count)<tr><td>{{ ucwords(str_replace('_',' ', $stage)) }}</td><td>{{ $count }}</td><td>@if(isset($metrics['v3']['stage_conversions'][$stage]))@php($c=$metrics['v3']['stage_conversions'][$stage]){{ $c['numerator'] }} / {{ $c['denominator'] }} · {{ $c['rate'] === null ? 'No eligible denominator' : round($c['rate']*100,1).'%' }}@else — @endif</td></tr>@endforeach
        </tbody></table></div>
        <p>Quiz confirmation mean: {{ $metrics['v3']['quiz_elapsed_seconds']['mean'] === null ? 'No confirmations' : round($metrics['v3']['quiz_elapsed_seconds']['mean']).' seconds' }}. Includes paused and background time.</p>
    </x-filament::section>
    <x-filament::section heading="Steps: observation and follow-up">
        <div role="region" tabindex="0" aria-label="Scrollable data table" style="overflow-x:auto"><table class="bs-table"><thead><tr><th>Version / step</th><th>Viewed / completed</th><th>Completed among viewed</th><th>Not completed after window</th><th>Pending</th><th>Completed without view</th></tr></thead><tbody>
        @forelse($metrics['steps'] as $step)<tr><td>{{ $step['configuration_version'] }}<br>{{ $step['step_id'] }}</td><td>{{ $step['viewed'] }} / {{ $step['completed'] }}</td><td>{{ $step['completion_among_viewed']['numerator'] }} / {{ $step['completion_among_viewed']['denominator'] }}</td><td>{{ $step['not_completed_after_observation'] }}</td><td>{{ $step['pending_observation'] }}</td><td>{{ $step['completed_without_recorded_view'] }}</td></tr>@empty<tr><td colspan="6">No recorded step observations.</td></tr>@endforelse
        </tbody></table></div><p style="margin-top:1rem">Observed noncompletion is not certain abandonment. Conditional steps use their own recorded views.</p>
    </x-filament::section>
    <x-filament::section heading="Quiz elapsed time">
        <p>{{ $metrics['quiz_elapsed_seconds']['mean'] === null ? 'No completed quizzes' : round($metrics['quiz_elapsed_seconds']['mean']).' seconds mean' }} · {{ $metrics['quiz_elapsed_seconds']['count'] }} completed quizzes. Wall-clock time includes background and paused time.</p>
    </x-filament::section>
    <x-filament::section heading="Landing experiment · insufficient evidence">
        <div role="region" tabindex="0" aria-label="Scrollable data table" style="overflow-x:auto"><table class="bs-table"><thead><tr><th>Variant</th><th>Exposed / eligible</th><th>Pending</th><th>Started / eligible</th><th>Quiz / eligible</th><th>Boundary / eligible</th></tr></thead><tbody>
        @foreach($metrics['experiment']['variants'] as $variant => $row)<tr><td>{{ $variant }}</td><td>{{ $row['exposed'] }} / {{ $row['eligible_exposed'] }}</td><td>{{ $row['pending_observation'] }}</td><td>{{ $row['start_conversion']['numerator'] }} / {{ $row['start_conversion']['denominator'] }}</td><td>{{ $row['quiz_conversion']['numerator'] }} / {{ $row['quiz_conversion']['denominator'] }}</td><td>{{ $row['boundary_conversion']['numerator'] }} / {{ $row['boundary_conversion']['denominator'] }}</td></tr>@endforeach
        </tbody></table></div><p style="margin-top:1rem">First-exposure cohort; all rates use mature exposed visitors. No significance calculation or winner.</p>
    </x-filament::section>
    <x-filament::section heading="First-touch UTM · run cohort">
        <div role="region" tabindex="0" aria-label="Scrollable data table" style="overflow-x:auto"><table class="bs-table"><thead><tr><th>Attribution tuple</th><th>Runs</th><th>Quiz completed</th></tr></thead><tbody>
        @forelse($metrics['utm'] as $row)<tr><td>{{ json_encode($row['attribution'], JSON_UNESCAPED_SLASHES) }}</td><td>{{ $row['runs'] }}</td><td>{{ $row['quiz_completed'] }}</td></tr>@empty<tr><td colspan="3">No attributed runs.</td></tr>@endforelse
        </tbody></table></div>
    </x-filament::section>
    @else<p role="status">Apply a valid past range of at most 93 days and an observation window of 1–168 hours.</p>@endif
</x-filament-panels::page>
