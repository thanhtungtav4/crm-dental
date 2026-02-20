<x-filament::section>
    <div class="space-y-4">
        <div class="flex items-center gap-2">
            <x-filament::badge color="warning">Đang phát triển</x-filament::badge>
            <span class="text-sm text-gray-500">Module này đang được xây dựng theo tài liệu tham chiếu.</span>
        </div>

        @if($subheading = $this->getSubheading())
            <p class="text-sm text-gray-600">{{ $subheading }}</p>
        @endif

        @php($bullets = $this->getBullets())
        @if(!empty($bullets))
            <ul class="list-disc pl-5 text-sm text-gray-600 space-y-1">
                @foreach($bullets as $bullet)
                    <li>{{ $bullet }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</x-filament::section>
