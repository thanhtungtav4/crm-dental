@props([
    'viewState',
])

<div class="space-y-6">
    @if(! empty($viewState['stats_panel']['cards']))
        <section class="space-y-3" aria-labelledby="{{ $viewState['stats_panel']['labelled_by'] }}">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div class="space-y-1">
                    <h2 id="{{ $viewState['stats_panel']['labelled_by'] }}" class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        {{ $viewState['stats_panel']['heading'] }}
                    </h2>
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        {{ $viewState['stats_panel']['description'] }}
                    </p>
                </div>

                <span class="inline-flex w-fit items-center rounded-full bg-primary-50 px-3 py-1 text-xs font-medium text-primary-700 ring-1 ring-primary-600/10 dark:bg-primary-950/40 dark:text-primary-300 dark:ring-primary-400/20">
                    {{ count($viewState['stats_panel']['cards']) }} chỉ số
                </span>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4" role="list">
                @foreach($viewState['stats_panel']['cards'] as $card)
                    @include('filament.pages.reports.partials.report-stat-card', [
                        'card' => $card,
                    ])
                @endforeach
            </div>
        </section>
    @endif

    <div>
        {{ $this->table }}
    </div>
</div>
