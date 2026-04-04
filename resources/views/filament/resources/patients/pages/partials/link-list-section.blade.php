@props([
    'section',
])

<div class="crm-feature-card">
    <h4 class="crm-feature-subtitle">{{ $section['title'] }}</h4>
    <div class="crm-link-list">
        @forelse($section['links'] as $link)
            <a href="{{ $link['url'] }}"
                target="{{ $link['target'] }}"
                class="crm-link-list-item">
                <span class="crm-link-list-item-text">
                    {{ $link['title'] }}
                </span>
                <span class="crm-link-list-item-action">{{ $link['action_label'] }}</span>
            </a>
        @empty
            <p class="crm-link-list-empty">{{ $section['empty_text'] }}</p>
        @endforelse
    </div>
</div>
