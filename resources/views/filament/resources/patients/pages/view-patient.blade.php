<x-filament-panels::page>
    <div
        class="crm-patient-record-page"
        x-data="{
            activeTab: $wire.entangle('activeTab'),
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
                            {{ strtoupper(substr($this->record->full_name, 0, 1)) }}{{ strtoupper(substr(explode(' ', $this->record->full_name)[count(explode(' ', $this->record->full_name)) - 1] ?? '', 0, 1)) }}
                        </div>
                        {{-- Name & Basic Info --}}
                        <div class="crm-patient-identity">
                            <div class="crm-patient-identity-row">
                                <h2 class="crm-patient-name">
                                    {{ $this->record->full_name }}</h2>
                                @if($this->record->gender === 'male')
                                    <span class="crm-patient-gender-badge is-male">Nam</span>
                                @elseif($this->record->gender === 'female')
                                    <span class="crm-patient-gender-badge is-female">Nữ</span>
                                @endif
                            </div>
                            <p class="crm-patient-code">
                                {{ $this->record->patient_code }}</p>
                            @if($this->record->phone)
                                <a href="tel:{{ $this->record->phone }}" class="crm-patient-phone-chip">
                                    <svg class="crm-patient-phone-chip-icon" fill="currentColor" viewBox="0 0 20 20">
                                        <path
                                            d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z" />
                                    </svg>
                                    <span class="crm-patient-phone-chip-text">{{ $this->record->phone }}</span>
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
                {{-- Info Grid with Cards --}}
                <div class="crm-patient-overview-body">
                    <div class="crm-patient-info-grid">
                        {{-- Phone Card --}}
                        <div class="crm-patient-info-card is-phone">
                            <div class="crm-patient-info-card-head">
                                <div class="crm-patient-info-icon">
                                    <svg class="crm-patient-info-icon-svg" fill="currentColor" viewBox="0 0 20 20">
                                        <path
                                            d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z" />
                                    </svg>
                                </div>
                                <span class="crm-patient-info-label">Điện thoại</span>
                            </div>
                            <p class="crm-patient-info-value">
                                @if($this->record->phone)
                                    <a href="tel:{{ $this->record->phone }}"
                                        class="crm-patient-info-link">{{ $this->record->phone }}</a>
                                @else
                                    <span class="crm-patient-info-muted">Chưa có</span>
                                @endif
                            </p>
                        </div>
                        {{-- Email Card --}}
                        <div class="crm-patient-info-card is-email">
                            <div class="crm-patient-info-card-head">
                                <div class="crm-patient-info-icon">
                                    <svg class="crm-patient-info-icon-svg" fill="currentColor" viewBox="0 0 20 20">
                                        <path
                                            d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" />
                                        <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" />
                                    </svg>
                                </div>
                                <span class="crm-patient-info-label">Email</span>
                            </div>
                            <p class="crm-patient-info-value is-truncate" title="{{ $this->record->email }}">
                                @if($this->record->email)
                                    <a href="mailto:{{ $this->record->email }}"
                                        class="crm-patient-info-link">{{ $this->record->email }}</a>
                                @else
                                    <span class="crm-patient-info-muted">Chưa có</span>
                                @endif
                            </p>
                        </div>
                        {{-- Birthday Card --}}
                        <div class="crm-patient-info-card is-birthday">
                            <div class="crm-patient-info-card-head">
                                <div class="crm-patient-info-icon">
                                    <svg class="crm-patient-info-icon-svg" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z"
                                            clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <span class="crm-patient-info-label">Ngày sinh</span>
                            </div>
                            <p class="crm-patient-info-value">
                                @if($this->record->birthday)
                                    {{ \Carbon\Carbon::parse($this->record->birthday)->format('d/m/Y') }}
                                    <span class="crm-patient-info-age">({{ \Carbon\Carbon::parse($this->record->birthday)->age }}
                                        tuổi)</span>
                                @else
                                    <span class="crm-patient-info-muted">Chưa có</span>
                                @endif
                            </p>
                        </div>
                        {{-- Branch Card --}}
                        <div class="crm-patient-info-card is-branch">
                            <div class="crm-patient-info-card-head">
                                <div class="crm-patient-info-icon">
                                    <svg class="crm-patient-info-icon-svg" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a1 1 0 110 2h-3a1 1 0 01-1-1v-2a1 1 0 00-1-1H9a1 1 0 00-1 1v2a1 1 0 01-1 1H4a1 1 0 110-2V4z"
                                            clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <span class="crm-patient-info-label">Chi nhánh</span>
                            </div>
                            <p class="crm-patient-info-value is-branch">
                                {{ $this->record->branch?->name ?? 'Chưa phân bổ' }}
                            </p>
                        </div>
                    </div>
                    @if($this->record->address)
                        {{-- Address Card --}}
                        <div class="crm-patient-address-card is-address">
                            <div class="crm-patient-info-card-head">
                                <div class="crm-patient-info-icon">
                                    <svg class="crm-patient-info-icon-svg" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z"
                                            clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <span class="crm-patient-info-label">Địa chỉ</span>
                            </div>
                            <p class="crm-patient-address-value">
                                {{ $this->record->address }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Tabs Navigation --}}
        <div class="crm-section-block">
            <div class="crm-top-tabs-shell">
                <nav x-ref="topTabs" class="crm-top-tabs crm-top-tabs-nav" aria-label="Tabs">
                    @foreach($this->tabs as $tab)
                        <button
                            wire:click="setActiveTab('{{ $tab['id'] }}')"
                            class="crm-top-tab {{ $activeTab === $tab['id'] ? 'is-active' : '' }}"
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
            <div>
                @if($activeTab === 'basic-info')
                    <div class="crm-pane-stack-lg" wire:key="patient-{{ $this->record->id }}-basic-info">
                        @if($this->record)
                            <div>
                                @livewire(\App\Filament\Resources\Patients\Widgets\PatientOverviewWidget::class, ['record' => $this->record], key('patient-' . $this->record->id . '-overview'))
                            </div>

                            <div class="crm-feature-card">
                                <div class="crm-feature-card-head">
                                    <div>
                                        <h3 class="crm-feature-card-title">Người liên hệ</h3>
                                        <p class="crm-feature-card-description">Tách danh sách người liên hệ để lễ tân/CSKH thao tác nhanh theo từng bệnh nhân.</p>
                                    </div>
                                </div>
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
                                        <h3 class="crm-history-card-title">Lịch sử thao tác</h3>
                                        <p class="crm-history-card-description">Xem timeline cập nhật ở tab chuyên biệt để tránh trùng lặp nội dung.</p>
                                    </div>
                                    <button type="button"
                                        wire:click="setActiveTab('activity-log')"
                                        class="crm-btn crm-btn-primary crm-btn-md">
                                        Mở lịch sử thao tác
                                    </button>
                                </div>
                            </div>
                        @else
                            <div class="crm-empty-inline">
                                <p>Không thể tải dữ liệu bệnh nhân</p>
                            </div>
                        @endif
                    </div>
                @elseif($activeTab === 'exam-treatment')
                    <div class="crm-pane-stack-lg" wire:key="patient-{{ $this->record->id }}-exam-treatment">
                        @livewire('patient-exam-form', ['patient' => $this->record], key('patient-' . $this->record->id . '-exam-form'))

                        @livewire('patient-treatment-plan-section', ['patientId' => $this->record->id], key('patient-' . $this->record->id . '-treatment-plan'))

                        <div class="crm-treatment-progress-stack">
                            <div class="crm-treatment-progress-head">
                                <h3 class="crm-section-label">Tiến trình điều trị</h3>
                                <span class="crm-section-badge">{{ $this->treatmentProgressDayCount }} ngày · {{ $this->treatmentProgressCount }} phiên</span>
                            </div>

                            <div class="crm-treatment-card">
                                <div class="crm-treatment-subhead">
                                    <div class="crm-treatment-subhead-title">Tiến trình điều trị</div>
                                    <div class="crm-treatment-subhead-actions">
                                        <span class="crm-treatment-subhead-count">Tổng chi phí phiên: {{ $this->treatmentProgressTotalAmountFormatted }}đ</span>
                                        <a href="{{ route('filament.admin.resources.treatment-sessions.create', ['patient_id' => $this->record->id]) }}"
                                           class="crm-btn crm-btn-primary crm-btn-md"
                                           style="color: #ffffff;"
                                        >
                                            Thêm ngày điều trị
                                        </a>
                                    </div>
                                </div>

                                @if($this->treatmentProgressDayCount > 0)
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
                                                        @if($session['edit_url'])
                                                            <a href="{{ $session['edit_url'] }}" class="crm-table-icon-btn" title="Chỉnh sửa phiên điều trị">
                                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="crm-icon-14">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16.5 3.5a2.12 2.12 0 113 3L7 19l-4 1 1-4 12.5-12.5z" />
                                                                </svg>
                                                            </a>
                                                        @else
                                                            <span class="text-xs text-gray-400">-</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="11" class="crm-treatment-empty">
                                                        Chưa có tiến trình điều trị cho bệnh nhân này.
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
                        <div class="crm-feature-card">
                            <div class="crm-feature-card-head">
                                <div>
                                    <h3 class="crm-feature-card-title">Xưởng/Labo</h3>
                                    <p class="crm-feature-card-description">Theo dõi lệnh labo theo hồ sơ bệnh nhân và tiến độ giao hàng.</p>
                                </div>
                                <a
                                    href="{{ route('filament.admin.resources.factory-orders.create', ['patient_id' => $this->record->id, 'branch_id' => $this->record->first_branch_id]) }}"
                                    class="crm-btn crm-btn-primary crm-btn-md">
                                    Tạo lệnh labo
                                </a>
                            </div>
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
                                                <td>{{ $order->ordered_at?->format('d/m/Y H:i') ?? '-' }}</td>
                                                <td>{{ $order->due_at?->format('d/m/Y H:i') ?? '-' }}</td>
                                                <td>{{ strtoupper((string) $order->priority) }}</td>
                                                <td>
                                                    <span
                                                        class="crm-treatment-status-badge {{ match ($order->status) {
                                                            \App\Models\FactoryOrder::STATUS_DELIVERED => 'is-completed',
                                                            \App\Models\FactoryOrder::STATUS_ORDERED, \App\Models\FactoryOrder::STATUS_IN_PROGRESS => 'is-progress',
                                                            default => 'is-default',
                                                        } }}"
                                                    >
                                                        {{ match ($order->status) {
                                                            \App\Models\FactoryOrder::STATUS_ORDERED => 'Đã đặt',
                                                            \App\Models\FactoryOrder::STATUS_IN_PROGRESS => 'Đang làm',
                                                            \App\Models\FactoryOrder::STATUS_DELIVERED => 'Đã giao',
                                                            \App\Models\FactoryOrder::STATUS_CANCELLED => 'Đã hủy',
                                                            default => 'Nháp',
                                                        } }}
                                                    </span>
                                                </td>
                                                <td>{{ number_format((int) ($order->items_count ?? 0), 0, ',', '.') }}</td>
                                                <td>
                                                    <a
                                                        href="{{ route('filament.admin.resources.factory-orders.edit', ['record' => $order->id]) }}"
                                                        class="text-sm font-medium text-primary-600 hover:underline"
                                                    >
                                                        Chi tiết
                                                    </a>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="crm-feature-table-empty">
                                                    Chưa có lệnh labo cho bệnh nhân này.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="crm-feature-card">
                            <div class="crm-feature-card-head">
                                <div>
                                    <h3 class="crm-feature-card-title">Phiếu xuất vật tư</h3>
                                    <p class="crm-feature-card-description">Xuất kho theo bệnh nhân, đồng bộ tồn kho và chi phí vật tư.</p>
                                </div>
                                <a
                                    href="{{ route('filament.admin.resources.material-issue-notes.create', ['patient_id' => $this->record->id, 'branch_id' => $this->record->first_branch_id]) }}"
                                    class="crm-btn crm-btn-primary crm-btn-md"
                                >
                                    Tạo phiếu xuất
                                </a>
                            </div>
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
                                                <td>{{ $note->issued_at?->format('d/m/Y H:i') ?? '-' }}</td>
                                                <td>
                                                    <span
                                                        class="crm-treatment-status-badge {{ match ($note->status) {
                                                            \App\Models\MaterialIssueNote::STATUS_POSTED => 'is-completed',
                                                            default => 'is-default',
                                                        } }}"
                                                    >
                                                        {{ match ($note->status) {
                                                            \App\Models\MaterialIssueNote::STATUS_POSTED => 'Đã xuất kho',
                                                            \App\Models\MaterialIssueNote::STATUS_CANCELLED => 'Đã hủy',
                                                            default => 'Nháp',
                                                        } }}
                                                    </span>
                                                </td>
                                                <td>{{ number_format((int) ($note->items_count ?? 0), 0, ',', '.') }}</td>
                                                <td class="is-emphasis">{{ number_format((float) ($note->total_cost ?? 0), 0, ',', '.') }}đ</td>
                                                <td>{{ $note->reason ?: '-' }}</td>
                                                <td>
                                                    <a
                                                        href="{{ route('filament.admin.resources.material-issue-notes.edit', ['record' => $note->id]) }}"
                                                        class="text-sm font-medium text-primary-600 hover:underline"
                                                    >
                                                        Chi tiết
                                                    </a>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="crm-feature-table-empty">
                                                    Chưa có phiếu xuất vật tư cho bệnh nhân này.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="crm-feature-card">
                            <div class="crm-feature-card-head">
                                <div>
                                    <h3 class="crm-feature-card-title">Vật tư đã dùng trong phiên điều trị</h3>
                                    <p class="crm-feature-card-description">Đối soát vật tư đã sử dụng trực tiếp theo từng phiên điều trị.</p>
                                </div>
                                <a href="{{ route('filament.admin.resources.treatment-materials.create') }}"
                                    class="crm-btn crm-btn-outline crm-btn-md">
                                    Thêm vật tư phiên
                                </a>
                            </div>
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
                                                <td>{{ $usage->created_at?->format('d/m/Y H:i') ?? '-' }}</td>
                                                <td>#{{ $usage->treatment_session_id ?? '-' }}</td>
                                                <td class="is-emphasis">{{ $usage->material?->name ?? 'N/A' }}</td>
                                                <td>{{ number_format((float) $usage->quantity, 0, ',', '.') }}</td>
                                                <td>{{ number_format((float) $usage->cost, 0, ',', '.') }}đ</td>
                                                <td class="is-emphasis">{{ number_format((float) $usage->quantity * (float) $usage->cost, 0, ',', '.') }}đ</td>
                                                <td>{{ $usage->user?->name ?? 'N/A' }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="crm-feature-table-empty">
                                                    Chưa có dữ liệu vật tư cho bệnh nhân này.
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
                                <h3 class="crm-payment-summary-title">Thông tin thanh toán</h3>
                                <div class="crm-payment-summary-actions">
                                    <div class="crm-payment-balance">
                                        Số dư:
                                        <strong class="{{ $this->paymentSummary['balance_is_positive'] ? 'is-positive' : 'is-negative' }}">
                                            {{ $this->paymentSummary['balance_amount_formatted'] }}đ
                                        </strong>
                                    </div>
                                    <a href="{{ $this->paymentSummary['create_payment_url'] }}" class="crm-btn crm-btn-primary crm-btn-md">
                                        Phiếu thu
                                    </a>
                                    <a href="{{ $this->paymentSummary['create_payment_url'] }}" class="crm-btn crm-btn-outline crm-btn-md">
                                        Thanh toán
                                    </a>
                                </div>
                            </div>

                            <div class="crm-payment-metrics">
                                <div class="crm-payment-metric">
                                    <span>Tổng tiền điều trị</span>
                                    <strong>{{ $this->paymentSummary['total_treatment_amount_formatted'] }}</strong>
                                </div>
                                <div class="crm-payment-metric">
                                    <span>Giảm giá</span>
                                    <strong>{{ $this->paymentSummary['total_discount_amount_formatted'] }}</strong>
                                </div>
                                <div class="crm-payment-metric">
                                    <span>Phải thanh toán</span>
                                    <strong>{{ $this->paymentSummary['must_pay_amount_formatted'] }}</strong>
                                </div>
                                <div class="crm-payment-metric">
                                    <span>Đã thu</span>
                                    <strong class="is-positive">{{ $this->paymentSummary['net_collected_amount_formatted'] }}</strong>
                                </div>
                                <div class="crm-payment-metric">
                                    <span>Còn lại</span>
                                    <strong class="is-negative">{{ $this->paymentSummary['remaining_amount_formatted'] }}</strong>
                                </div>
                            </div>
                        </div>

                        <div class="crm-payment-block">
                            <div class="crm-payment-block-title">HÓA ĐƠN ĐIỀU TRỊ</div>
                            @livewire(\App\Filament\Resources\Patients\RelationManagers\InvoicesRelationManager::class, [
                                'ownerRecord' => $this->record,
                                'pageClass' => static::class,
                            ])
                        </div>

                        <div class="crm-payment-block">
                            <div class="crm-payment-block-title">DANH SÁCH PHIẾU THU - HOÀN ỨNG</div>
                            @livewire(\App\Filament\Resources\Patients\RelationManagers\PatientPaymentsRelationManager::class, [
                                'ownerRecord' => $this->record,
                                'pageClass' => static::class,
                            ])
                        </div>
                    </div>
                @elseif($activeTab === 'forms')
                    <div class="crm-pane-stack" wire:key="patient-{{ $this->record->id }}-forms">
                        <div class="crm-feature-card">
                            <h3 class="crm-feature-card-title">Biểu mẫu & tài liệu</h3>
                            <p class="crm-feature-card-description">
                                Truy cập nhanh biểu mẫu in theo hồ sơ bệnh nhân (đơn thuốc, hóa đơn, phiếu thu).
                            </p>
                        </div>

                        <div class="crm-forms-grid">
                            <div class="crm-feature-card">
                                <h4 class="crm-feature-subtitle">Đơn thuốc gần nhất</h4>
                                <div class="crm-link-list">
                                    @forelse($this->latestPrescriptions as $prescription)
                                        <a href="{{ route('prescriptions.print', $prescription) }}"
                                            target="_blank"
                                            class="crm-link-list-item">
                                            <span class="crm-link-list-item-text">
                                                {{ $prescription->prescription_code }} - {{ $prescription->treatment_date?->format('d/m/Y') ?? '-' }}
                                            </span>
                                            <span class="crm-link-list-item-action">In</span>
                                        </a>
                                    @empty
                                        <p class="crm-link-list-empty">Chưa có đơn thuốc để in.</p>
                                    @endforelse
                                </div>
                            </div>
                            <div class="crm-feature-card">
                                <h4 class="crm-feature-subtitle">Hóa đơn gần nhất</h4>
                                <div class="crm-link-list">
                                    @forelse($this->latestInvoices as $invoice)
                                        <a href="{{ route('invoices.print', $invoice) }}"
                                            target="_blank"
                                            class="crm-link-list-item">
                                            <span class="crm-link-list-item-text">
                                                #{{ $invoice->invoice_no }} - {{ $invoice->issued_at?->format('d/m/Y') ?? $invoice->created_at?->format('d/m/Y') }}
                                            </span>
                                            <span class="crm-link-list-item-action">In</span>
                                        </a>
                                    @empty
                                        <p class="crm-link-list-empty">Chưa có hóa đơn để in.</p>
                                    @endforelse
                                </div>
                            </div>
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

    </div>
</x-filament-panels::page>
