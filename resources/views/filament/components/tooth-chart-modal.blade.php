<style>
    .crm-tooth-condition-default {
        color: #666;
    }

    @foreach($chart['palette'] as $paletteCondition)
        .crm-tooth-condition-{{ $paletteCondition['id'] }} {
            color: {{ $paletteCondition['color'] }};
        }
    @endforeach
</style>

<div class="tooth-chart-modal p-4">
    @include('filament.components.partials.tooth-chart-modal-legend', ['legendItems' => $chart['legend']])

    @include('filament.components.partials.tooth-chart-modal-rows', ['rows' => $chart['rows']])

    @include('filament.components.partials.tooth-chart-modal-summary', ['chart' => $chart])

    <p class="mt-4 text-center text-xs text-gray-400">
        {{ $chart['footer_note'] }}
    </p>
</div>
