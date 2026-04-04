@props([
    'section',
])

<div class="crm-feature-card">
    @include('filament.resources.patients.pages.partials.section-header', [
        'title' => $section['title'],
        'description' => $section['description'],
        'action' => $section['action'],
    ])
</div>

<div class="crm-feature-table-card">
    <div class="crm-feature-table-wrap">
        <table class="crm-feature-table">
            <thead>
                <tr>
                    @foreach($section['table']['columns'] as $column)
                        <th>{{ $column['label'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($section['table']['rows'] as $row)
                    <tr>
                        @foreach($row['cells'] as $cell)
                            <td @class([$cell['td_class'] ?? null])>
                                @if($cell['type'] === 'badge')
                                    <span class="{{ $cell['badge_class'] }}">
                                        {{ $cell['value'] }}
                                    </span>
                                @elseif($cell['type'] === 'action')
                                    @include('filament.resources.patients.pages.partials.detail-action', [
                                        'url' => $cell['url'],
                                        'actionClass' => $cell['action_class'],
                                        'label' => $cell['label'],
                                    ])
                                @else
                                    {{ $cell['value'] }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $section['table']['empty_colspan'] }}" class="crm-feature-table-empty">
                            {{ $section['table']['empty_text'] }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
