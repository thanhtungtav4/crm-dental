@props([
    'prompt',
])

<div class="crm-history-card">
    <div class="crm-history-card-inner">
        <div>
            <h3 class="crm-history-card-title">{{ $prompt['title'] }}</h3>
            <p class="crm-history-card-description">{{ $prompt['description'] }}</p>
        </div>
        <button type="button"
            wire:click="setActiveTab('{{ $prompt['action']['tab'] }}')"
            class="{{ $prompt['action']['button_class'] }}">
            {{ $prompt['action']['label'] }}
        </button>
    </div>
</div>
