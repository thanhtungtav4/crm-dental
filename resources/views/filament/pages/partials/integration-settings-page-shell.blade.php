@props([
    'viewState',
])

<form wire:submit="save" class="space-y-6">
    @foreach($viewState['form_panel']['notice_panels'] as $notice)
        @include('filament.pages.partials.integration-settings-notice', [
            'classes' => $notice['classes'],
            'message' => $notice['message'],
        ])
    @endforeach

    @include('filament.pages.partials.control-plane-section-list', [
        'sections' => $viewState['form_panel']['pre_sections'],
    ])

    @include('filament.pages.partials.integration-settings-revision-notice', [
        'notice' => $viewState['form_panel']['revision_conflict_notice'],
    ])

    @include('filament.pages.partials.control-plane-section-list', [
        'sections' => $viewState['form_panel']['provider_sections'],
    ])

    @include('filament.pages.partials.integration-settings-submit-bar', [
        'action' => $viewState['form_panel']['submit_action'],
    ])
</form>

@include('filament.pages.partials.control-plane-section-list', [
    'sections' => $viewState['post_form_sections'],
])
