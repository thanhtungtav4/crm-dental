@if($action['is_visible'])
    <div class="flex justify-end">
        <x-filament::button type="submit" :icon="$action['icon']">
            {{ $action['label'] }}
        </x-filament::button>
    </div>
@endif
