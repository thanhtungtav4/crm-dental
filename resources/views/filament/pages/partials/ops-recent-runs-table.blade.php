@if($panel['is_empty'])
    @include('filament.pages.partials.ops-empty-state', [
        'message' => $panel['empty_state_message'],
        'containerClasses' => 'rounded-2xl border border-dashed border-gray-300 px-4 py-6 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400',
    ])
@else
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
            <thead>
                <tr class="text-left text-xs font-semibold uppercase tracking-[0.08em] text-gray-500 dark:text-gray-400">
                    <th class="px-3 py-2">Command</th>
                    <th class="px-3 py-2">Status</th>
                    <th class="px-3 py-2">Actor</th>
                    <th class="px-3 py-2">Occurred</th>
                    <th class="px-3 py-2">Summary</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-900">
                @foreach($panel['rows'] as $run)
                    <tr>
                        <td class="px-3 py-2 font-mono text-xs text-gray-700 dark:text-gray-200">{{ $run['command'] }}</td>
                        <td class="px-3 py-2">
                            <span class="{{ $run['status_badge_classes'] }} inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold">
                                {{ $run['status'] }}
                            </span>
                        </td>
                        <td class="px-3 py-2 text-gray-700 dark:text-gray-200">{{ $run['actor'] }}</td>
                        <td class="px-3 py-2 text-gray-500 dark:text-gray-400">{{ $run['occurred_at'] }}</td>
                        <td class="px-3 py-2 text-gray-500 dark:text-gray-400">{{ $run['summary'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
