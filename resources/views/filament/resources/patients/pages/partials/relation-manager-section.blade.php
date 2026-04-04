@props([
    'section',
    'relationManager',
    'ownerRecord',
    'pageClass',
    'wireKey',
])

<div class="crm-feature-card">
    @include('filament.resources.patients.pages.partials.section-header', [
        'title' => $section['title'],
        'description' => $section['description'],
        'action' => null,
    ])
    <div class="mt-4">
        @livewire($relationManager, [
            'ownerRecord' => $ownerRecord,
            'pageClass' => $pageClass,
        ], key($wireKey))
    </div>
</div>
