<?php

namespace App\Filament\Pages;

trait BuildsControlCenterPageViewState
{
    /**
     * @return array{
     *   overview_panel:array{
     *     cards:array<int, array<string, mixed>>
     *   },
     *   quick_links_panel:array{
     *     heading:string,
     *     description:string,
     *     grid_classes:string,
     *     links:array<int, array<string, mixed>>
     *   },
     *   sections_panel:array{
     *     sections:array<int, array<string, mixed>>
     *   }
     * }
     */
    protected function buildControlCenterPageViewState(
        string $quickLinksHeading,
        string $quickLinksDescription,
        string $quickLinksGridClasses,
    ): array {
        return [
            'overview_panel' => [
                'cards' => $this->renderedControlCenterOverviewCards(),
            ],
            'quick_links_panel' => [
                'heading' => $quickLinksHeading,
                'description' => $quickLinksDescription,
                'grid_classes' => $quickLinksGridClasses,
                'links' => $this->controlCenterQuickLinks(),
            ],
            'sections_panel' => [
                'sections' => $this->renderedControlCenterSections(),
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function controlCenterOverviewCards(): array
    {
        return array_values((array) ($this->state['overview_cards'] ?? []));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function controlCenterQuickLinks(): array
    {
        return array_values((array) ($this->state['quick_links'] ?? []));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function controlCenterSections(): array
    {
        return array_values((array) ($this->state['sections'] ?? []));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function renderedControlCenterOverviewCards(): array
    {
        return array_map(function (array $card): array {
            $tone = (string) ($card['tone'] ?? 'gray');

            return [
                ...$card,
                'tone' => $tone,
                'status_badge_classes' => $this->controlCenterToneBadgeClass($tone),
            ];
        }, $this->controlCenterOverviewCards());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function renderedControlCenterSections(): array
    {
        return array_map(function (array $section): array {
            return [
                ...$section,
                'empty_state_text' => 'Chưa có dữ liệu trong scope hiện tại.',
                'metrics' => array_map(function (array $metric): array {
                    $tone = (string) ($metric['tone'] ?? 'gray');

                    return [
                        ...$metric,
                        'tone' => $tone,
                        'badge_classes' => $this->controlCenterToneBadgeClass($tone),
                    ];
                }, (array) ($section['metrics'] ?? [])),
                'rows' => array_map(function (array $row): array {
                    $tone = (string) ($row['tone'] ?? 'gray');

                    return [
                        ...$row,
                        'tone' => $tone,
                        'badge_classes' => $this->controlCenterToneBadgeClass($tone),
                    ];
                }, (array) ($section['rows'] ?? [])),
            ];
        }, $this->controlCenterSections());
    }

    protected function controlCenterToneBadgeClass(string $tone): string
    {
        return match ($tone) {
            'success' => 'border-success-200 bg-success-50 text-success-700 dark:border-success-900/60 dark:bg-success-950/30 dark:text-success-200',
            'warning' => 'border-warning-200 bg-warning-50 text-warning-700 dark:border-warning-900/60 dark:bg-warning-950/30 dark:text-warning-200',
            'danger' => 'border-danger-200 bg-danger-50 text-danger-700 dark:border-danger-900/60 dark:bg-danger-950/30 dark:text-danger-200',
            'info' => 'border-info-200 bg-info-50 text-info-700 dark:border-info-900/60 dark:bg-info-950/30 dark:text-info-200',
            default => 'border-gray-200 bg-gray-50 text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200',
        };
    }
}
