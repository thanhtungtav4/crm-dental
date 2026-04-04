<x-filament::section
    :heading="$section['heading'] ?? null"
    :description="$section['description'] ?? null"
    :class="$section['section_classes'] ?? null"
>
    @include($section['partial'], $section['include_data'] ?? [])
</x-filament::section>
