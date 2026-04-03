<div class="space-y-3">
    @foreach($commands as $command)
        <div class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 font-mono text-xs text-gray-700 dark:border-gray-800 dark:bg-gray-900/60 dark:text-gray-200">
            {{ $command }}
        </div>
    @endforeach
</div>
