<button
    type="button"
    class="{{ $buttonClass }}"
    x-on:click.prevent="copyToClipboard(@js($copyValue), @js($copyLabel))"
    title="{{ $actionLabel }}"
    aria-label="{{ $actionLabel }}"
>
    <x-filament::icon icon="heroicon-o-square-2-stack" class="crm-icon-16" />
</button>
