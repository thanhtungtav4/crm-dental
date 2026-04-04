<div class="mt-4 flex flex-wrap gap-2">
    @foreach($actions as $action)
        <x-filament::button type="button" :color="$action['color']" :icon="$action['icon']" wire:click="{{ $action['wire_click'] }}">
            {{ $action['label'] }}
        </x-filament::button>
    @endforeach
</div>
