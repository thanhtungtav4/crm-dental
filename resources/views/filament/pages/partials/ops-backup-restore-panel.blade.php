<div class="space-y-4">
    @include('filament.pages.partials.ops-runtime-backup-panel', [
        'panel' => $runtimeBackupPanel,
    ])

    @include('filament.pages.partials.ops-artifact-list', [
        'artifacts' => $backupFixturesPanel['items'],
        'listClasses' => 'ops-grid-3',
    ])
</div>
