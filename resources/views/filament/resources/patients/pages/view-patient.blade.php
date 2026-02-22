<x-filament-panels::page>
    <div class="crm-patient-record-page" x-data="{ activeTab: $wire.entangle('activeTab') }" x-init="
        const tabQueryMap = {
            payments: 'payment',
            appointments: 'appointment',
        };

        $watch('activeTab', (val) => {
            const url = new URL(window.location);
            url.searchParams.set('tab', tabQueryMap[val] ?? val);
            window.history.replaceState({}, '', url);
        });
    ">
        {{-- Patient Overview Card --}}
        <div class="mb-8">
            <div
                class="crm-patient-overview-card rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden">
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
                                    {{ $this->record->phone }}
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
                {{-- Info Grid with Cards --}}
                <div class="p-6">
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
        <div class="mb-8">
            <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 px-4 py-3">
                @php
                    $tpTotal = $this->record->treatmentPlans()->count();
                    $invTotal = $this->record->invoices()->count();
                    $appointmentTotal = $this->record->appointments()->count();
                    $notesCount = $this->record->notes()->count();
                    $clinicalNotesCount = $this->record->clinicalNotes()->count();
                    $photosCount = $this->record->photos()->count();
                    $prescriptionCount = $this->record->prescriptions()->count();
                    $paymentCount = $this->record->payments()->count();
                    $materialCount = \App\Models\TreatmentMaterial::query()
                        ->whereHas('session.treatmentPlan', fn($query) => $query->where('patient_id', $this->record->id))
                        ->count();
                    $activityCount = $this->record->appointments()->count()
                        + $this->record->treatmentPlans()->count()
                        + $this->record->invoices()->count()
                        + $this->record->payments()->count()
                        + $this->record->notes()->count()
                        + $this->record->branchLogs()->count();
                @endphp
                <nav class="crm-top-tabs mt-2" aria-label="Tabs">
                    @php
                        $tabs = [
                            ['id' => 'basic-info', 'label' => 'Thông tin cơ bản', 'count' => null],
                            ['id' => 'exam-treatment', 'label' => 'Khám & Điều trị', 'count' => $clinicalNotesCount + $tpTotal],
                            ['id' => 'prescriptions', 'label' => 'Đơn thuốc', 'count' => $prescriptionCount],
                            ['id' => 'photos', 'label' => 'Thư viện ảnh', 'count' => $photosCount],
                            ['id' => 'lab-materials', 'label' => 'Xưởng/Vật tư', 'count' => $materialCount],
                            ['id' => 'appointments', 'label' => 'Lịch hẹn', 'count' => $appointmentTotal],
                            ['id' => 'payments', 'label' => 'Thanh toán', 'count' => $invTotal + $paymentCount],
                            ['id' => 'forms', 'label' => 'Biểu mẫu', 'count' => $prescriptionCount + $invTotal],
                            ['id' => 'care', 'label' => 'Chăm sóc', 'count' => $notesCount],
                            ['id' => 'activity-log', 'label' => 'Lịch sử thao tác', 'count' => $activityCount],
                        ];
                    @endphp

                    @foreach($tabs as $tab)
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
                    <div class="space-y-6" wire:key="patient-{{ $this->record->id }}-basic-info">
                        @if($this->record)
                            <div>
                                @livewire(\App\Filament\Resources\Patients\Widgets\PatientOverviewWidget::class, ['record' => $this->record], key('patient-' . $this->record->id . '-overview'))
                            </div>

                            <div class="rounded-xl border border-dashed border-gray-300 dark:border-gray-700 p-5 bg-white dark:bg-gray-900">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Lịch sử thao tác</h3>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">Xem timeline cập nhật ở tab chuyên biệt để tránh trùng lặp nội dung.</p>
                                    </div>
                                    <button type="button"
                                        wire:click="setActiveTab('activity-log')"
                                        class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">
                                        Mở lịch sử thao tác
                                    </button>
                                </div>
                            </div>
                        @else
                            <div class="text-center py-12">
                                <p class="text-gray-500">Không thể tải dữ liệu bệnh nhân</p>
                            </div>
                        @endif
                    </div>
                @elseif($activeTab === 'exam-treatment')
                    @php
                        $formatMoney = fn($value) => number_format((float) $value, 0, ',', '.');

                        $treatmentProgress = $this->record->treatmentSessions()
                            ->with(['doctor:id,name', 'assistant:id,name', 'planItem:id,name,tooth_number,tooth_ids,quantity,price,status'])
                            ->latest('performed_at')
                            ->latest('id')
                            ->limit(50)
                            ->get();

                    @endphp
                    <div class="space-y-6" wire:key="patient-{{ $this->record->id }}-exam-treatment">
                        @livewire('patient-exam-form', ['patient' => $this->record], key('patient-' . $this->record->id . '-exam-form'))

                        @livewire('patient-treatment-plan-section', ['patientId' => $this->record->id], key('patient-' . $this->record->id . '-treatment-plan'))

                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <h3 class="crm-section-label">Tiến trình điều trị</h3>
                                <span class="crm-section-badge">{{ $treatmentProgress->count() }} phiên</span>
                            </div>

                            <div class="crm-treatment-card rounded-md border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                                <div class="crm-treatment-subhead flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                                    <div class="crm-treatment-subhead-title text-sm font-semibold text-gray-900 dark:text-white">Tiến trình điều trị</div>
                                    <div class="flex items-center gap-2">
                                        <span class="crm-treatment-subhead-count text-xs text-gray-600 dark:text-gray-300">Hiển thị {{ $treatmentProgress->count() }}/{{ $treatmentProgress->count() }}</span>
                                        <a href="{{ route('filament.admin.resources.treatment-sessions.create', ['patient_id' => $this->record->id]) }}"
                                           class="crm-btn crm-btn-primary h-8 px-3 text-xs"
                                        >
                                            Thêm ngày điều trị
                                        </a>
                                    </div>
                                </div>

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
                                                <th>Tình trạng</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($treatmentProgress as $session)
                                                @php
                                                    $performedAt = $session->performed_at ?? $session->start_at ?? $session->created_at;
                                                    $statusLabel = match ($session->status) {
                                                        'done', 'completed' => 'Hoàn thành',
                                                        'follow_up' => 'Tái khám',
                                                        default => 'Đã lên lịch',
                                                    };
                                                    $statusClass = match ($session->status) {
                                                        'done', 'completed' => 'is-completed',
                                                        'follow_up' => 'is-progress',
                                                        default => 'is-default',
                                                    };
                                                    $toothLabel = $session->planItem?->tooth_number ?: (is_array($session->planItem?->tooth_ids) ? implode(' ', $session->planItem?->tooth_ids) : '-');
                                                    $sessionQty = $session->planItem?->quantity ?? 1;
                                                    $sessionPrice = (float) ($session->planItem?->price ?? 0);
                                                @endphp
                                                <tr>
                                                    <td>{{ $performedAt?->format('d/m/Y H:i') ?? '-' }}</td>
                                                    <td>{{ $toothLabel }}</td>
                                                    <td>{{ $session->planItem?->name ?? '-' }}</td>
                                                    <td>{{ $session->procedure ?: ($session->notes ?: '-') }}</td>
                                                    <td>{{ $session->doctor?->name ?? '-' }}</td>
                                                    <td>{{ $session->assistant?->name ?? '-' }}</td>
                                                    <td class="is-center">{{ $sessionQty }}</td>
                                                    <td class="is-right">{{ $formatMoney($sessionPrice) }}</td>
                                                    <td>
                                                        <span class="crm-treatment-status {{ $statusClass }}">
                                                            {{ $statusLabel }}
                                                        </span>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="9" class="crm-treatment-empty">
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
                    @php
                        $materialUsages = \App\Models\TreatmentMaterial::query()
                            ->with(['session', 'material', 'user'])
                            ->whereHas('session.treatmentPlan', fn($query) => $query->where('patient_id', $this->record->id))
                            ->latest('created_at')
                            ->limit(100)
                            ->get();
                    @endphp
                    <div class="space-y-4" wire:key="patient-{{ $this->record->id }}-lab-materials">
                        <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-5">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Danh sách vật tư tiêu hao</h3>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Theo dõi vật tư đã sử dụng trong các phiên điều trị của bệnh nhân.</p>
                                </div>
                                <a href="{{ route('filament.admin.resources.treatment-materials.create') }}"
                                    class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">
                                    Thêm phiếu xuất
                                </a>
                            </div>
                        </div>

                        <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                                    <thead class="bg-gray-50 dark:bg-gray-800">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Ngày xuất</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Phiên điều trị</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Tên vật tư</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Số lượng</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Đơn giá</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Tổng tiền</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Người xuất</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800 bg-white dark:bg-gray-900">
                                        @forelse($materialUsages as $usage)
                                            <tr>
                                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $usage->created_at?->format('d/m/Y H:i') ?? '-' }}</td>
                                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">#{{ $usage->treatment_session_id ?? '-' }}</td>
                                                <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-white">{{ $usage->material?->name ?? 'N/A' }}</td>
                                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ number_format((float) $usage->quantity, 0, ',', '.') }}</td>
                                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ number_format((float) $usage->cost, 0, ',', '.') }}đ</td>
                                                <td class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-white">{{ number_format((float) $usage->quantity * (float) $usage->cost, 0, ',', '.') }}đ</td>
                                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $usage->user?->name ?? 'N/A' }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
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
                    @php
                        $totalTreatmentAmount = (float) $this->record->invoices()->sum('total_amount');
                        $totalDiscountAmount = (float) $this->record->invoices()->sum('discount_amount');
                        $mustPayAmount = max(0, $totalTreatmentAmount - $totalDiscountAmount);
                        $receiptAmount = (float) $this->record->payments()->where('direction', 'receipt')->sum('amount');
                        $refundAmount = abs((float) $this->record->payments()->where('direction', 'refund')->sum('amount'));
                        $netCollectedAmount = $receiptAmount - $refundAmount;
                        $remainingAmount = max(0, $mustPayAmount - $netCollectedAmount);
                        $balanceAmount = $netCollectedAmount - $mustPayAmount;
                        $openInvoice = $this->record->invoices()
                            ->whereNotIn('status', ['paid', 'cancelled'])
                            ->latest('created_at')
                            ->first();
                        $latestInvoice = $openInvoice ?: $this->record->invoices()->latest('created_at')->first();
                        $createPaymentUrl = route(
                            'filament.admin.resources.payments.create',
                            $latestInvoice ? ['invoice_id' => $latestInvoice->id] : []
                        );
                    @endphp
                    <div class="crm-payment-tab space-y-4" wire:key="patient-{{ $this->record->id }}-payments">
                        <div class="crm-payment-summary">
                            <div class="crm-payment-summary-head">
                                <h3 class="crm-payment-summary-title">Thông tin thanh toán</h3>
                                <div class="crm-payment-summary-actions">
                                    <div class="crm-payment-balance">
                                        Số dư:
                                        <strong class="{{ $balanceAmount >= 0 ? 'is-positive' : 'is-negative' }}">
                                            {{ number_format($balanceAmount, 0, ',', '.') }}đ
                                        </strong>
                                    </div>
                                    <a href="{{ $createPaymentUrl }}" class="crm-btn crm-btn-primary h-8 px-3 text-xs">
                                        Phiếu thu
                                    </a>
                                    <a href="{{ $createPaymentUrl }}" class="crm-btn crm-btn-outline h-8 px-3 text-xs">
                                        Thanh toán
                                    </a>
                                </div>
                            </div>

                            <div class="crm-payment-metrics">
                                <div class="crm-payment-metric">
                                    <span>Tổng tiền điều trị</span>
                                    <strong>{{ number_format($totalTreatmentAmount, 0, ',', '.') }}</strong>
                                </div>
                                <div class="crm-payment-metric">
                                    <span>Giảm giá</span>
                                    <strong>{{ number_format($totalDiscountAmount, 0, ',', '.') }}</strong>
                                </div>
                                <div class="crm-payment-metric">
                                    <span>Phải thanh toán</span>
                                    <strong>{{ number_format($mustPayAmount, 0, ',', '.') }}</strong>
                                </div>
                                <div class="crm-payment-metric">
                                    <span>Đã thu</span>
                                    <strong class="is-positive">{{ number_format($netCollectedAmount, 0, ',', '.') }}</strong>
                                </div>
                                <div class="crm-payment-metric">
                                    <span>Còn lại</span>
                                    <strong class="is-negative">{{ number_format($remainingAmount, 0, ',', '.') }}</strong>
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
                    @php
                        $latestPrescriptions = $this->record->prescriptions()->latest('created_at')->limit(5)->get();
                        $latestInvoices = $this->record->invoices()->latest('created_at')->limit(5)->get();
                    @endphp
                    <div class="space-y-4" wire:key="patient-{{ $this->record->id }}-forms">
                        <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-5">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Biểu mẫu & tài liệu</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Truy cập nhanh biểu mẫu in theo hồ sơ bệnh nhân (đơn thuốc, hóa đơn, phiếu thu).
                            </p>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                            <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-5">
                                <h4 class="text-base font-semibold text-gray-900 dark:text-white mb-3">Đơn thuốc gần nhất</h4>
                                <div class="space-y-2">
                                    @forelse($latestPrescriptions as $prescription)
                                        <a href="{{ route('prescriptions.print', $prescription) }}"
                                            target="_blank"
                                            class="flex items-center justify-between rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-800">
                                            <span class="text-sm text-gray-700 dark:text-gray-300">
                                                {{ $prescription->prescription_code }} - {{ $prescription->treatment_date?->format('d/m/Y') ?? '-' }}
                                            </span>
                                            <span class="text-xs font-semibold text-primary-600">In</span>
                                        </a>
                                    @empty
                                        <p class="text-sm text-gray-500">Chưa có đơn thuốc để in.</p>
                                    @endforelse
                                </div>
                            </div>
                            <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-5">
                                <h4 class="text-base font-semibold text-gray-900 dark:text-white mb-3">Hóa đơn gần nhất</h4>
                                <div class="space-y-2">
                                    @forelse($latestInvoices as $invoice)
                                        <a href="{{ route('invoices.print', $invoice) }}"
                                            target="_blank"
                                            class="flex items-center justify-between rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-800">
                                            <span class="text-sm text-gray-700 dark:text-gray-300">
                                                #{{ $invoice->invoice_no }} - {{ $invoice->issued_at?->format('d/m/Y') ?? $invoice->created_at?->format('d/m/Y') }}
                                            </span>
                                            <span class="text-xs font-semibold text-primary-600">In</span>
                                        </a>
                                    @empty
                                        <p class="text-sm text-gray-500">Chưa có hóa đơn để in.</p>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>
                @elseif($activeTab === 'care')
                    <div class="crm-care-tab space-y-4" wire:key="patient-{{ $this->record->id }}-care">
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
