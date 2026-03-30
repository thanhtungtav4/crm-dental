<x-filament-panels::page>
    <div
        class="crm-patient-record-page"
        x-data="{
            activeTab: $wire.entangle('activeTab'),
            copiedMessage: null,
            copyTimer: null,
            ensureActiveTabVisible() {
                this.$nextTick(() => {
                    const tabs = this.$refs.topTabs;
                    if (! tabs) {
                        return;
                    }

                    const active = tabs.querySelector('.crm-top-tab.is-active');
                    if (! active) {
                        return;
                    }

                    const tabItems = Array.from(tabs.querySelectorAll('.crm-top-tab'));
                    const activeIndex = tabItems.indexOf(active);

                    if (activeIndex <= 1) {
                        tabs.scrollTo({
                            left: 0,
                            behavior: 'auto',
                        });

                        return;
                    }

                    if (activeIndex >= (tabItems.length - 2)) {
                        tabs.scrollTo({
                            left: tabs.scrollWidth,
                            behavior: 'auto',
                        });

                        return;
                    }

                    active.scrollIntoView({
                        behavior: 'auto',
                        block: 'nearest',
                        inline: 'center',
                    });
                });
            },
            copyToClipboard(value, label) {
                if (! value) {
                    return;
                }

                const writeClipboard = () => {
                    if (window.navigator?.clipboard?.writeText) {
                        return window.navigator.clipboard.writeText(value);
                    }

                    const tempInput = document.createElement('textarea');
                    tempInput.value = value;
                    tempInput.setAttribute('readonly', '');
                    tempInput.style.position = 'fixed';
                    tempInput.style.opacity = '0';
                    document.body.appendChild(tempInput);
                    tempInput.select();
                    document.execCommand('copy');
                    document.body.removeChild(tempInput);

                    return Promise.resolve();
                };

                writeClipboard().then(() => {
                    this.copiedMessage = `${label} đã được sao chép`;

                    if (this.copyTimer) {
                        window.clearTimeout(this.copyTimer);
                    }

                    this.copyTimer = window.setTimeout(() => {
                        this.copiedMessage = null;
                    }, 1400);
                });
            },
        }"
        x-init="
            const tabQueryMap = {
                payments: 'payment',
                appointments: 'appointment',
            };

            const syncTabQuery = (val) => {
                const url = new URL(window.location);
                url.searchParams.set('tab', tabQueryMap[val] ?? val);
                window.history.replaceState({}, '', url);
            };

            syncTabQuery(activeTab);
            ensureActiveTabVisible();

            $watch('activeTab', (val) => {
                syncTabQuery(val);
                ensureActiveTabVisible();
            });
        "
    >
        {{-- Patient Overview Card --}}
        <div class="crm-section-block">
            <div
                class="crm-patient-overview-card">
                <div class="crm-patient-overview-header">
                    <div class="crm-patient-overview-header-inner">
                        {{-- Avatar --}}
                        <div class="crm-patient-avatar">
                            {{ $this->identityHeader['avatar_initials'] }}
                        </div>
                        {{-- Name & Basic Info --}}
                        <div class="crm-patient-identity">
                            <div class="crm-patient-identity-row">
                                <h2 class="crm-patient-name">
                                    {{ $this->identityHeader['full_name'] }}</h2>
                                @if($this->identityHeader['gender_label'])
                                    <span class="crm-patient-gender-badge {{ $this->identityHeader['gender_badge_class'] }}">{{ $this->identityHeader['gender_label'] }}</span>
                                @endif
                            </div>
                            <div class="crm-copy-inline-row">
                                <p class="crm-patient-code">{{ $this->identityHeader['patient_code'] }}</p>
                                @if($this->identityHeader['patient_code'])
                                    @include('filament.resources.patients.pages.partials.copy-button', [
                                        'buttonClass' => 'crm-copy-icon-btn is-light',
                                        'copyValue' => $this->identityHeader['patient_code'],
                                        'copyLabel' => $this->identityHeader['patient_code_copy_label'],
                                        'actionLabel' => $this->identityHeader['patient_code_copy_action_label'],
                                    ])
                                @endif
                            </div>
                            @if($this->identityHeader['phone'])
                                <div class="crm-copy-inline-row">
                                    <a href="{{ $this->identityHeader['phone_href'] }}" class="crm-patient-phone-chip" style="color: #ffffff;">
                                        <svg class="crm-patient-phone-chip-icon" fill="currentColor" viewBox="0 0 20 20">
                                            <path
                                                d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z" />
                                        </svg>
                                        <span class="crm-patient-phone-chip-text" style="color: #ffffff;">{{ $this->identityHeader['phone'] }}</span>
                                    </a>
                                    @include('filament.resources.patients.pages.partials.copy-button', [
                                        'buttonClass' => 'crm-copy-icon-btn is-light',
                                        'copyValue' => $this->identityHeader['phone'],
                                        'copyLabel' => $this->identityHeader['phone_copy_label'],
                                        'actionLabel' => $this->identityHeader['phone_copy_action_label'],
                                    ])
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
                {{-- Info Grid with Cards --}}
                <div class="crm-patient-overview-body">
                    <div class="crm-patient-info-grid">
                        @foreach($this->basicInfoGrid['cards'] as $card)
                            @include('filament.resources.patients.pages.partials.info-card', ['card' => $card])
                        @endforeach
                    </div>
                    @if($this->basicInfoGrid['address_card'])
                        <div class="crm-patient-address-card is-address">
                            <div class="crm-patient-info-card-head">
                                <div class="crm-patient-info-icon">
                                    <x-filament::icon :icon="$this->basicInfoGrid['address_card']['icon']" class="crm-patient-info-icon-svg" />
                                </div>
                                <span class="crm-patient-info-label">{{ $this->basicInfoGrid['address_card']['label'] }}</span>
                            </div>
                            <p class="crm-patient-address-value">
                                {{ $this->basicInfoGrid['address_card']['value'] }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Tabs Navigation --}}
        <div class="crm-section-block">
            <div class="crm-top-tabs-shell">
                <nav x-ref="topTabs" class="crm-top-tabs crm-top-tabs-nav" role="tablist" aria-label="Khu vực làm việc hồ sơ bệnh nhân">
                    @foreach($this->renderedTabs as $tab)
                        <button
                            type="button"
                            id="{{ $tab['button_id'] }}"
                            wire:click="setActiveTab('{{ $tab['id'] }}')"
                            role="tab"
                            aria-selected="{{ $tab['aria_selected'] }}"
                            aria-controls="{{ $tab['panel_id'] }}"
                            tabindex="{{ $tab['tabindex'] }}"
                            class="{{ $tab['button_class'] }}"
                        >
                            <span>{{ $tab['label'] }}</span>
                            @if($tab['count'] !== null)
                                <span class="crm-top-tab-count">{{ $tab['count'] }}</span>
                            @endif
                        </button>
                    @endforeach
                </nav>
            </div>


            {{-- Tab Content --}}
            <div
                id="{{ $this->activePanelId }}"
                role="tabpanel"
                aria-labelledby="{{ $this->activeTabButtonId }}"
                tabindex="0"
            >
                @if($activeTab === 'basic-info')
                    <div class="crm-pane-stack-lg" wire:key="patient-{{ $this->record->id }}-basic-info">
                        @if($this->record)
                            <div>
                                @livewire(\App\Filament\Resources\Patients\Widgets\PatientOverviewWidget::class, ['record' => $this->record], key('patient-' . $this->record->id . '-overview'))
                            </div>

                            <div class="crm-feature-card">
                                @include('filament.resources.patients.pages.partials.section-header', [
                                    'title' => $this->basicInfoPanels['contacts']['title'],
                                    'description' => $this->basicInfoPanels['contacts']['description'],
                                    'action' => null,
                                ])
                                <div class="mt-4">
                                    @livewire(\App\Filament\Resources\Patients\RelationManagers\ContactsRelationManager::class, [
                                        'ownerRecord' => $this->record,
                                        'pageClass' => static::class,
                                    ])
                                </div>
                            </div>

                            <div class="crm-history-card">
                                <div class="crm-history-card-inner">
                                    <div>
                                        <h3 class="crm-history-card-title">{{ $this->basicInfoPanels['activity_log']['title'] }}</h3>
                                        <p class="crm-history-card-description">{{ $this->basicInfoPanels['activity_log']['description'] }}</p>
                                    </div>
                                    <button type="button"
                                        wire:click="setActiveTab('{{ $this->basicInfoPanels['activity_log']['action']['tab'] }}')"
                                        class="{{ $this->basicInfoPanels['activity_log']['action']['button_class'] }}">
                                        {{ $this->basicInfoPanels['activity_log']['action']['label'] }}
                                    </button>
                                </div>
                            </div>
                        @else
                            <div class="crm-empty-inline">
                                <p>{{ $this->basicInfoPanels['empty_state_text'] }}</p>
                            </div>
                        @endif
                    </div>
                @elseif($activeTab === 'exam-treatment')
                    <div class="crm-pane-stack-lg" wire:key="patient-{{ $this->record->id }}-exam-treatment">
                        @livewire('patient-exam-form', ['patient' => $this->record], key('patient-' . $this->record->id . '-exam-form'))

                        @livewire('patient-treatment-plan-section', ['patientId' => $this->record->id], key('patient-' . $this->record->id . '-treatment-plan'))

                        <div class="crm-treatment-progress-stack">
                            <div class="crm-treatment-progress-head">
                                <h3 class="crm-section-label">{{ $this->treatmentProgressPanel['section_title'] }}</h3>
                                <span class="crm-section-badge">{{ $this->treatmentProgressPanel['summary_badge'] }}</span>
                            </div>

                            <div class="crm-treatment-card">
                                <div class="crm-treatment-subhead">
                                    <div class="crm-treatment-subhead-title">{{ $this->treatmentProgressPanel['card_title'] }}</div>
                                    <div class="crm-treatment-subhead-actions">
                                        <span class="crm-treatment-subhead-count">{{ $this->treatmentProgressPanel['total_amount_text'] }}</span>
                                        @if($this->treatmentProgressPanel['primary_action'])
                                            <a
                                                href="{{ $this->treatmentProgressPanel['primary_action']['url'] }}"
                                                class="crm-btn {{ $this->treatmentProgressPanel['primary_action']['button_class'] }} crm-btn-md"
                                                style="color: #ffffff;"
                                            >
                                                {{ $this->treatmentProgressPanel['primary_action']['label'] }}
                                            </a>
                                        @endif
                                    </div>
                                </div>

                                @if($this->treatmentProgressPanel['has_day_summaries'])
                                    <div class="crm-treatment-table-wrap">
                                        <table class="crm-treatment-table">
                                            <thead>
                                                <tr>
                                                    <th>Ngày điều trị</th>
                                                    <th class="is-center">Số phiên</th>
                                                    <th class="is-right">Tổng chi phí ngày</th>
                                                    <th>Tình trạng</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($this->treatmentProgressDaySummaries as $summary)
                                                    <tr>
                                                        <td>{{ $summary['progress_date'] }}</td>
                                                        <td class="is-center">{{ $summary['sessions_count'] }}</td>
                                                        <td class="is-right">{{ $summary['day_total_amount_formatted'] }}đ</td>
                                                        <td>{{ $summary['status_label'] }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif

                                <div class="crm-treatment-table-wrap">
                                    <table class="crm-treatment-table">
                                        <thead>
                                            <tr>
                                                <th>Ngày điều trị</th>
                                                <th>Răng số</th>
                                                <th>Thủ thuật</th>
                                                <th>Nội dung thủ thuật</th>
                                                <th>Bác sĩ</th>
                                                <th>Trợ thủ</th>
                                                <th class="is-center">S.L</th>
                                                <th class="is-right">Đơn giá</th>
                                                <th class="is-right">Thành tiền</th>
                                                <th>Tình trạng</th>
                                                <th class="is-center">Thao tác</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($this->treatmentProgress as $session)
                                                <tr>
                                                    <td>{{ $session['performed_at'] }}</td>
                                                    <td>{{ $session['tooth_label'] }}</td>
                                                    <td>{{ $session['plan_item_name'] }}</td>
                                                    <td>{{ $session['procedure'] }}</td>
                                                    <td>{{ $session['doctor_name'] }}</td>
                                                    <td>{{ $session['assistant_name'] }}</td>
                                                    <td class="is-center">{{ $session['quantity'] }}</td>
                                                    <td class="is-right">{{ $session['price_formatted'] }}</td>
                                                    <td class="is-right">{{ $session['total_amount_formatted'] }}</td>
                                                    <td>
                                                        <span class="crm-treatment-status {{ $session['status_class'] }}">
                                                            {{ $session['status_label'] }}
                                                        </span>
                                                    </td>
                                                    <td class="is-center">
                                                        @if($session['edit_action'])
                                                            <a
                                                                href="{{ $session['edit_action']['url'] }}"
                                                                class="{{ $session['edit_action']['button_class'] }}"
                                                                title="{{ $session['edit_action']['label'] }}"
                                                                aria-label="{{ $session['edit_action']['label'] }}"
                                                            >
                                                                <x-filament::icon :icon="$session['edit_action']['icon']" class="crm-icon-14" />
                                                            </a>
                                                        @else
                                                            <span class="text-xs text-gray-400">{{ $session['edit_action_placeholder'] }}</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="11" class="crm-treatment-empty">
                                                        {{ $this->treatmentProgressPanel['empty_text'] }}
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                @elseif($activeTab === 'prescriptions')
                    <div class="crm-rel-tab crm-rel-tab-prescriptions" wire:key="patient-{{ $this->record->id }}-prescriptions">
                        @livewire(\App\Filament\Resources\Patients\RelationManagers\PrescriptionsRelationManager::class, [
                            'ownerRecord' => $this->record,
                            'pageClass' => static::class,
                        ])
                    </div>
                @elseif($activeTab === 'photos')
                    <div class="crm-rel-tab crm-rel-tab-photos" wire:key="patient-{{ $this->record->id }}-photos">
                        @livewire(\App\Filament\Resources\Patients\RelationManagers\PatientPhotosRelationManager::class, [
                            'ownerRecord' => $this->record,
                            'pageClass' => static::class,
                        ])
                    </div>
                @elseif($activeTab === 'lab-materials')
                    <div class="crm-pane-stack" wire:key="patient-{{ $this->record->id }}-lab-materials">
                        @php($factoryOrdersSection = $this->labMaterialSections['factory_orders'])
                        <div class="crm-feature-card">
                            @include('filament.resources.patients.pages.partials.section-header', [
                                'title' => $factoryOrdersSection['title'],
                                'description' => $factoryOrdersSection['description'],
                                'action' => $factoryOrdersSection['action'] === null
                                    ? null
                                    : [...$factoryOrdersSection['action'], 'style' => 'color: #ffffff;'],
                            ])
                        </div>

                        <div class="crm-feature-table-card">
                            <div class="crm-feature-table-wrap">
                                <table class="crm-feature-table">
                                    <thead>
                                        <tr>
                                            <th>Mã lệnh</th>
                                            <th>Ngày đặt</th>
                                            <th>Hẹn trả</th>
                                            <th>Ưu tiên</th>
                                            <th>Trạng thái</th>
                                            <th>Số item</th>
                                            <th>Thao tác</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($this->factoryOrders as $order)
                                            <tr>
                                                <td class="is-emphasis">{{ $order->order_no }}</td>
                                                <td>{{ $order['ordered_at_formatted'] }}</td>
                                                <td>{{ $order['due_at_formatted'] }}</td>
                                                <td>{{ strtoupper((string) $order['priority']) }}</td>
                                                <td>
                                                    <span class="crm-treatment-status-badge {{ $order['status_class'] }}">
                                                        {{ $order['status_label'] }}
                                                    </span>
                                                </td>
                                                <td>{{ $order['items_count_formatted'] }}</td>
                                                <td>
                                                    @include('filament.resources.patients.pages.partials.detail-action', [
                                                        'url' => $order['detail_action']['url'],
                                                        'actionClass' => $order['detail_action']['class'],
                                                        'label' => $order['detail_action']['label'],
                                                    ])
                                                </td>
                                            </tr>
                                            @empty
                                            <tr>
                                                <td colspan="7" class="crm-feature-table-empty">
                                                    {{ $factoryOrdersSection['empty_text'] }}
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        @php($materialIssueNotesSection = $this->labMaterialSections['material_issue_notes'])
                        <div class="crm-feature-card">
                            @include('filament.resources.patients.pages.partials.section-header', [
                                'title' => $materialIssueNotesSection['title'],
                                'description' => $materialIssueNotesSection['description'],
                                'action' => $materialIssueNotesSection['action'] === null
                                    ? null
                                    : [...$materialIssueNotesSection['action'], 'style' => 'color: #ffffff;'],
                            ])
                        </div>

                        <div class="crm-feature-table-card">
                            <div class="crm-feature-table-wrap">
                                <table class="crm-feature-table">
                                    <thead>
                                        <tr>
                                            <th>Mã phiếu</th>
                                            <th>Ngày xuất</th>
                                            <th>Trạng thái</th>
                                            <th>Số vật tư</th>
                                            <th>Tổng chi phí</th>
                                            <th>Lý do</th>
                                            <th>Thao tác</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($this->materialIssueNotes as $note)
                                            <tr>
                                                <td class="is-emphasis">{{ $note->note_no }}</td>
                                                <td>{{ $note['issued_at_formatted'] }}</td>
                                                <td>
                                                    <span class="crm-treatment-status-badge {{ $note['status_class'] }}">
                                                        {{ $note['status_label'] }}
                                                    </span>
                                                </td>
                                                <td>{{ $note['items_count_formatted'] }}</td>
                                                <td class="is-emphasis">{{ $note['total_cost_formatted'] }}đ</td>
                                                <td>{{ $note['reason'] ?: '-' }}</td>
                                                <td>
                                                    @include('filament.resources.patients.pages.partials.detail-action', [
                                                        'url' => $note['detail_action']['url'],
                                                        'actionClass' => $note['detail_action']['class'],
                                                        'label' => $note['detail_action']['label'],
                                                    ])
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="crm-feature-table-empty">
                                                    {{ $materialIssueNotesSection['empty_text'] }}
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        @php($treatmentMaterialsSection = $this->labMaterialSections['treatment_materials'])
                        <div class="crm-feature-card">
                            @include('filament.resources.patients.pages.partials.section-header', [
                                'title' => $treatmentMaterialsSection['title'],
                                'description' => $treatmentMaterialsSection['description'],
                                'action' => $treatmentMaterialsSection['action'],
                            ])
                        </div>

                        <div class="crm-feature-table-card">
                            <div class="crm-feature-table-wrap">
                                <table class="crm-feature-table">
                                    <thead>
                                        <tr>
                                            <th>Ngày xuất</th>
                                            <th>Phiên điều trị</th>
                                            <th>Tên vật tư</th>
                                            <th>Số lượng</th>
                                            <th>Đơn giá</th>
                                            <th>Tổng tiền</th>
                                            <th>Người xuất</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($this->materialUsages as $usage)
                                            <tr>
                                                <td>{{ $usage['created_at_formatted'] }}</td>
                                                <td>{{ $usage['treatment_session_label'] }}</td>
                                                <td class="is-emphasis">{{ $usage['material_name'] }}</td>
                                                <td>{{ $usage['quantity_formatted'] }}</td>
                                                <td>{{ $usage['unit_cost_formatted'] }}đ</td>
                                                <td class="is-emphasis">{{ $usage['total_cost_formatted'] }}đ</td>
                                                <td>{{ $usage['user_name'] }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="crm-feature-table-empty">
                                                    {{ $treatmentMaterialsSection['empty_text'] }}
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @elseif($activeTab === 'appointments')
                    <div class="crm-rel-tab crm-rel-tab-appointments" wire:key="patient-{{ $this->record->id }}-appointments">
                        @livewire(\App\Filament\Resources\Patients\RelationManagers\AppointmentsRelationManager::class, [
                            'ownerRecord' => $this->record,
                            'pageClass' => static::class,
                        ])
                    </div>
                @elseif($activeTab === 'payments')
                    <div class="crm-payment-tab" wire:key="patient-{{ $this->record->id }}-payments">
                        <div class="crm-payment-summary">
                            <div class="crm-payment-summary-head">
                                <h3 class="crm-payment-summary-title">{{ $this->paymentPanel['title'] }}</h3>
                                <div class="crm-payment-summary-actions">
                                    <div class="crm-payment-balance">
                                        {{ $this->paymentPanel['balance_text'] }}
                                        <strong class="{{ $this->paymentPanel['balance_class'] }}">
                                            {{ $this->paymentPanel['balance_amount_formatted'] }}đ
                                        </strong>
                                    </div>
                                    @foreach($this->paymentPanel['actions'] as $action)
                                        <a
                                            href="{{ $action['url'] }}"
                                            class="crm-btn {{ $action['button_class'] }} crm-btn-md"
                                        >
                                            {{ $action['label'] }}
                                        </a>
                                    @endforeach
                                </div>
                            </div>

                            <div class="crm-payment-metrics">
                                @foreach($this->paymentPanel['metrics'] as $metric)
                                    <div class="crm-payment-metric">
                                        <span>{{ $metric['label'] }}</span>
                                        <strong @class([$metric['value_class']])>{{ $metric['value'] }}</strong>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        @foreach($this->renderedPaymentBlocks as $block)
                            <div class="crm-payment-block">
                                <div class="crm-payment-block-title">{{ $block['title'] }}</div>
                                @livewire($block['relation_manager'], [
                                    'ownerRecord' => $this->record,
                                    'pageClass' => static::class,
                                ], key('patient-' . $this->record->id . '-payment-block-' . $block['key']))
                            </div>
                        @endforeach
                    </div>
                @elseif($activeTab === 'forms')
                    <div class="crm-pane-stack" wire:key="patient-{{ $this->record->id }}-forms">
                        <div class="crm-feature-card">
                            @include('filament.resources.patients.pages.partials.section-header', [
                                'title' => $this->formsPanel['title'],
                                'description' => $this->formsPanel['description'],
                                'action' => null,
                            ])
                        </div>

                        <div class="crm-forms-grid">
                            @foreach($this->renderedFormSections as $section)
                                <div class="crm-feature-card">
                                    <h4 class="crm-feature-subtitle">{{ $section['title'] }}</h4>
                                    <div class="crm-link-list">
                                        @forelse($section['links'] as $link)
                                            <a href="{{ $link['url'] }}"
                                                target="{{ $link['target'] }}"
                                                class="crm-link-list-item">
                                                <span class="crm-link-list-item-text">
                                                    {{ $link['title'] }}
                                                </span>
                                                <span class="crm-link-list-item-action">{{ $link['action_label'] }}</span>
                                            </a>
                                        @empty
                                            <p class="crm-link-list-empty">{{ $section['empty_text'] }}</p>
                                        @endforelse
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @elseif($activeTab === 'care')
                    <div class="crm-care-tab" wire:key="patient-{{ $this->record->id }}-care">
                        <div class="crm-care-manager">
                            @livewire(\App\Filament\Resources\Patients\Relations\PatientNotesRelationManager::class, [
                                'ownerRecord' => $this->record,
                                'pageClass' => static::class,
                            ])
                        </div>
                    </div>
                @elseif($activeTab === 'activity-log')
                    <div wire:key="patient-{{ $this->record->id }}-activity-log">
                        @livewire(\App\Filament\Resources\Patients\Widgets\PatientActivityTimelineWidget::class, [
                            'record' => $this->record,
                        ])
                    </div>
                @endif
            </div>

            <div
                x-cloak
                x-show="copiedMessage"
                x-transition.opacity.duration.150ms
                class="crm-copy-toast"
                x-text="copiedMessage"
            ></div>

    </div>
</x-filament-panels::page>
