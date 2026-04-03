<x-filament-panels::page>
    <style>
        .crm-catalog-switch-grid {
            display: grid;
            gap: 0.75rem;
            grid-template-columns: minmax(0, 1fr);
        }

        @media (min-width: 768px) {
            .crm-catalog-switch-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        .crm-catalog-switch {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            border-radius: 0.75rem;
            border: 1px solid #d1d5db;
            background: #fff;
            padding: 0.625rem 0.75rem;
            color: #111827;
            transition: border-color .15s ease, background-color .15s ease;
            text-align: left;
        }

        .crm-catalog-switch.is-on {
            border-color: color-mix(in srgb, var(--crm-primary, #4f46e5) 45%, #d1d5db);
            background: color-mix(in srgb, var(--crm-primary, #4f46e5) 10%, #fff);
        }

        .crm-catalog-switch__text {
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 0.125rem;
        }

        .crm-catalog-switch__title {
            font-size: 0.9rem;
            font-weight: 600;
            line-height: 1.2;
            color: #111827;
        }

        .crm-catalog-switch__hint {
            font-size: 0.75rem;
            color: #6b7280;
            line-height: 1.25;
        }

        .crm-catalog-toggle__switch {
            position: relative;
            display: inline-flex;
            height: 1.5rem;
            width: 2.75rem;
            align-items: center;
            border-radius: 9999px;
            background: #d1d5db;
            padding: 0.125rem;
            transition: background-color .15s ease;
        }

        .crm-catalog-switch.is-on .crm-catalog-toggle__switch {
            background: var(--crm-primary, #4f46e5);
        }

        .crm-catalog-toggle__thumb {
            height: 1.25rem;
            width: 1.25rem;
            border-radius: 9999px;
            background: #fff;
            box-shadow: 0 1px 2px rgba(0, 0, 0, .15);
            transform: translateX(0);
            transition: transform .15s ease;
        }

        .crm-catalog-switch.is-on .crm-catalog-toggle__thumb {
            transform: translateX(1.25rem);
        }

        .crm-catalog-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 0.5rem;
            border-radius: 0.5rem;
            border: 1px solid #e5e7eb;
            background: #fff;
            padding: 0.5rem;
        }

        .crm-catalog-row--disabled {
            opacity: 0.6;
        }

        .crm-catalog-row__field {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .crm-catalog-row__field--toggle {
            justify-content: center;
        }

        .crm-catalog-row__caption {
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: #6b7280;
        }

        .crm-catalog-row__actions {
            display: flex;
            justify-content: flex-start;
            align-items: center;
        }

        @media (min-width: 1024px) {
            .crm-catalog-row {
                grid-template-columns: minmax(0, 1.1fr) minmax(0, 3fr) minmax(0, 2.2fr) auto;
                align-items: center;
            }

            .crm-catalog-row.crm-catalog-row--without-toggle {
                grid-template-columns: minmax(0, 3fr) minmax(0, 2.2fr) auto;
            }

            .crm-catalog-row__actions {
                justify-content: flex-end;
            }

            .crm-catalog-row__caption {
                display: none;
            }
        }
    </style>

    @include('filament.pages.partials.integration-settings-page-shell', [
        'viewState' => $this->pageViewState,
    ])
</x-filament-panels::page>
