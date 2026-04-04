<div class="crm-patient-info-card {{ $card['card_class'] }}">
    <div class="crm-patient-info-card-head">
        <div class="crm-patient-info-icon">
            <x-filament::icon :icon="$card['icon']" class="crm-patient-info-icon-svg" />
        </div>
        <span class="crm-patient-info-label">{{ $card['label'] }}</span>
    </div>
    <p
        @class([
            'crm-patient-info-value',
            'is-truncate' => $card['is_truncate'],
            'is-branch' => $card['key'] === 'branch',
        ])
        @if($card['title']) title="{{ $card['title'] }}" @endif
    >
        @if($card['is_muted'])
            <span class="crm-patient-info-muted">{{ $card['value'] }}</span>
        @elseif($card['href'])
            <span @class(['crm-copy-value-row' => $card['copy_value']])>
                <a href="{{ $card['href'] }}" class="crm-patient-info-link">{{ $card['value'] }}</a>
                @if($card['copy_value'] && $card['copy_label'])
                    @include('filament.resources.patients.pages.partials.copy-button', [
                        'buttonClass' => 'crm-copy-icon-btn',
                        'copyValue' => $card['copy_value'],
                        'copyLabel' => $card['copy_label'],
                        'actionLabel' => $card['copy_action_label'],
                    ])
                @endif
            </span>
        @else
            {{ $card['value'] }}
            @if($card['meta'])
                <span class="crm-patient-info-age">{{ $card['meta'] }}</span>
            @endif
        @endif
    </p>
</div>
