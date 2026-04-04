@if($notice['is_visible'])
    <x-filament::section>
        <div class="{{ $notice['classes'] }}">
            {{ $notice['message'] }}
        </div>
    </x-filament::section>
@endif
