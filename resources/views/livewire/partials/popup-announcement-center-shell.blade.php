@props([
    'viewState',
])

<div wire:poll.visible.{{ $viewState['polling_interval'] }}s="refreshPending" aria-live="{{ $viewState['aria_live'] }}">
    @if($viewState['has_announcement'])
        @include('livewire.partials.popup-announcement-dialog', [
            'announcement' => $viewState['announcement'],
        ])
    @endif
</div>
