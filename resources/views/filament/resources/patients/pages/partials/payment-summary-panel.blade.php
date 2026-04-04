@props([
    'panel',
])

<div class="crm-payment-summary">
    <div class="crm-payment-summary-head">
        <h3 class="crm-payment-summary-title">{{ $panel['title'] }}</h3>
        <div class="crm-payment-summary-actions">
            <div class="crm-payment-balance">
                {{ $panel['balance_text'] }}
                <strong class="{{ $panel['balance_class'] }}">
                    {{ $panel['balance_amount_formatted'] }}đ
                </strong>
            </div>
            @foreach($panel['actions'] as $action)
                <a
                    href="{{ $action['url'] }}"
                    class="crm-btn {{ $action['button_class'] }} crm-btn-md"
                >
                    {{ $action['label'] }}
                </a>
            @endforeach
        </div>
    </div>

    <div class="crm-payment-metrics">
        @foreach($panel['metrics'] as $metric)
            <div class="crm-payment-metric">
                <span>{{ $metric['label'] }}</span>
                <strong @class([$metric['value_class']])>{{ $metric['value'] }}</strong>
            </div>
        @endforeach
    </div>
</div>
