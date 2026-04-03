@props([
    'schemaNotice',
])

<x-filament::section
    :heading="$schemaNotice['heading']"
    :description="$schemaNotice['description']"
>
    <div class="rounded-2xl border border-dashed border-warning-300 bg-warning-50 px-5 py-5 text-sm text-warning-900 dark:border-warning-900/60 dark:bg-warning-950/20 dark:text-warning-100">
        {{ $schemaNotice['message'] }}
    </div>
</x-filament::section>
