@props(['rows'])

@foreach($rows as $row)
    <div class="crm-tooth-grid" x-show="{{ $row['show_expression'] }}" x-cloak>
        @foreach($row['teeth'] as $tooth)
            <div class="selection-item" item-key="{{ $tooth }}" :class="isToothSelected({{ $tooth }}) ? 'is-selected' : ''">
                @if($row['number_position'] === 'top')
                    <div class="crm-tooth-number mb-1">{{ $tooth }}</div>
                @endif
                <button
                    type="button"
                    class="tooth-item-cell"
                    :title="'{{ $row['tooth_prefix'] }} ' + {{ $tooth }} + ': ' + getConditionsList({{ $tooth }}) + ' | ' + getToothTreatmentStateLabel({{ $tooth }})"
                    @click="toggleTooth({{ $tooth }}, $event)"
                >
                    <div :class="getToothBoxClass({{ $tooth }}{{ $row['is_child'] ? ', true' : '' }})">
                        @if($row['show_status_list'])
                            <span class="tooth-status-list" x-text="getConditionLabels({{ $tooth }})"></span>
                        @endif
                    </div>
                </button>
                @if($row['number_position'] === 'bottom')
                    <div class="crm-tooth-number mt-1">{{ $tooth }}</div>
                @endif
            </div>
        @endforeach
    </div>
@endforeach
