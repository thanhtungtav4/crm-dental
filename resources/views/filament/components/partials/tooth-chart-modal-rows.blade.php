@props(['rows'])

<div class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
    @foreach($rows as $row)
        <div @class(['mb-4' => ! $loop->last])>
            <div class="mb-1 text-center text-xs text-gray-500">{{ $row['label'] }}</div>
            <div class="flex justify-center gap-1">
                @if($row['has_spacer'])
                    <div class="w-20"></div>
                @endif

                @foreach($row['teeth'] as $tooth)
                    <button
                        type="button"
                        class="{{ $tooth['button_classes'] }}"
                        data-tooth="{{ $tooth['number'] }}"
                        title="{{ $tooth['tooltip'] }}"
                    >
                        <span class="block">{{ $tooth['number'] }}</span>
                        @if($tooth['has_conditions'])
                            <span class="{{ $tooth['code_classes'] }}">
                                {{ $tooth['display_code'] }}
                            </span>
                        @endif
                    </button>
                @endforeach

                @if($row['has_spacer'])
                    <div class="w-20"></div>
                @endif
            </div>

            @if($loop->index === 1)
                <div class="my-4 border-t-2 border-dashed border-gray-300"></div>
            @endif
        </div>
    @endforeach
</div>
