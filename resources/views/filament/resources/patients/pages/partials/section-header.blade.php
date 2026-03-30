<div class="crm-feature-card-head">
    <div>
        <h3 class="crm-feature-card-title">{{ $title }}</h3>
        <p class="crm-feature-card-description">{{ $description }}</p>
    </div>

    @if($action !== null)
        <a
            href="{{ $action['url'] }}"
            class="crm-btn {{ $action['button_class'] }} crm-btn-md"
            @if(! empty($action['style'])) style="{{ $action['style'] }}" @endif
        >
            {{ $action['label'] }}
        </a>
    @endif
</div>
