<x-filament-panels::page>
    <style>
        .ops-page-shell {
            container-type: inline-size;
        }

        .ops-overview-grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: minmax(0, 1fr);
        }

        @container (min-width: 48rem) {
            .ops-overview-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @container (min-width: 96rem) {
            .ops-overview-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
        }

        .ops-detail-grid {
            display: grid;
            gap: 1.5rem;
            grid-template-columns: minmax(0, 1fr);
        }

        @media (min-width: 96rem) {
            .ops-detail-grid {
                grid-template-columns: minmax(0, 1.15fr) minmax(0, 0.85fr);
            }
        }

        .ops-column {
            container-type: inline-size;
            min-width: 0;
        }

        .ops-grid-2,
        .ops-grid-3 {
            display: grid;
            gap: 1rem;
            grid-template-columns: minmax(0, 1fr);
        }

        @container (min-width: 42rem) {
            .ops-grid-2 {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @container (min-width: 56rem) {
            .ops-grid-3 {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        .ops-break-words {
            overflow-wrap: anywhere;
            word-break: break-word;
        }
    </style>

    @include('filament.pages.partials.ops-control-center-shell', [
        'viewState' => $this->dashboardViewState,
    ])
</x-filament-panels::page>
