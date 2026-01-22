<x-filament-panels::page>
    <div x-data="{ activeTab: $wire.entangle('activeTab') }" x-init="
        $watch('activeTab', (val) => {
            const url = new URL(window.location);
            url.searchParams.set('tab', val);
            window.history.replaceState({}, '', url);
        });
    ">
        {{-- Patient Overview Card --}}
        <div class="mb-8">
            <div
                class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden">
                {{-- Header with inline style for reliability --}}
                <div style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); padding: 24px 32px;">
                    <div style="display: flex; align-items: center; gap: 24px;">
                        {{-- Avatar --}}
                        <div
                            style="width: 72px; height: 72px; min-width: 72px; background: rgba(255,255,255,0.2); border: 3px solid rgba(255,255,255,0.4); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 24px; font-weight: 700; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
                            {{ strtoupper(substr($this->record->full_name, 0, 1)) }}{{ strtoupper(substr(explode(' ', $this->record->full_name)[count(explode(' ', $this->record->full_name)) - 1] ?? '', 0, 1)) }}
                        </div>
                        {{-- Name & Basic Info --}}
                        <div style="flex: 1; min-width: 0;">
                            <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                                <h2 style="font-size: 22px; font-weight: 700; color: white; margin: 0;">
                                    {{ $this->record->full_name }}</h2>
                                @if($this->record->gender === 'male')
                                    <span
                                        style="background: rgba(96,165,250,0.5); padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; color: white;">Nam</span>
                                @elseif($this->record->gender === 'female')
                                    <span
                                        style="background: rgba(244,114,182,0.5); padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; color: white;">Nữ</span>
                                @endif
                            </div>
                            <p style="color: rgba(255,255,255,0.8); font-size: 14px; margin: 6px 0 0 0;">
                                {{ $this->record->patient_code }}</p>
                            @if($this->record->phone)
                                <a href="tel:{{ $this->record->phone }}"
                                    style="display: inline-flex; align-items: center; gap: 8px; margin-top: 12px; background: rgba(255,255,255,0.15); padding: 8px 16px; border-radius: 8px; color: white; font-size: 14px; font-weight: 500; text-decoration: none; transition: background 0.2s;"
                                    onmouseover="this.style.background='rgba(255,255,255,0.25)'"
                                    onmouseout="this.style.background='rgba(255,255,255,0.15)'">
                                    <svg style="width: 16px; height: 16px;" fill="currentColor" viewBox="0 0 20 20">
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
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px;"
                        class="lg:!grid-cols-4">
                        {{-- Phone Card --}}
                        <div style="background: #f8fafc; border-radius: 12px; padding: 16px;"
                            class="dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                            <div class="flex items-center gap-3 mb-2">
                                <div style="width: 32px; height: 32px; background: #dbeafe; border-radius: 8px; display: flex; align-items: center; justify-content: center;"
                                    class="dark:bg-blue-900/30">
                                    <svg style="color: #2563eb; width: 16px; height: 16px;" class="dark:text-blue-400"
                                        fill="currentColor" viewBox="0 0 20 20">
                                        <path
                                            d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z" />
                                    </svg>
                                </div>
                                <span
                                    style="color: #6b7280; font-size: 11px; text-transform: uppercase; font-weight: 500;">Điện
                                    thoại</span>
                            </div>
                            <p style="color: #111827; font-weight: 600; font-size: 14px;" class="dark:text-white">
                                @if($this->record->phone)
                                    <a href="tel:{{ $this->record->phone }}"
                                        class="hover:text-blue-600">{{ $this->record->phone }}</a>
                                @else
                                    <span style="color: #9ca3af;">Chưa có</span>
                                @endif
                            </p>
                        </div>
                        {{-- Email Card --}}
                        <div style="background: #f8fafc; border-radius: 12px; padding: 16px;"
                            class="dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                            <div class="flex items-center gap-3 mb-2">
                                <div style="width: 32px; height: 32px; background: #dcfce7; border-radius: 8px; display: flex; align-items: center; justify-content: center;"
                                    class="dark:bg-green-900/30">
                                    <svg style="color: #16a34a; width: 16px; height: 16px;" class="dark:text-green-400"
                                        fill="currentColor" viewBox="0 0 20 20">
                                        <path
                                            d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" />
                                        <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" />
                                    </svg>
                                </div>
                                <span
                                    style="color: #6b7280; font-size: 11px; text-transform: uppercase; font-weight: 500;">Email</span>
                            </div>
                            <p style="color: #111827; font-weight: 600; font-size: 14px;"
                                class="dark:text-white truncate" title="{{ $this->record->email }}">
                                @if($this->record->email)
                                    <a href="mailto:{{ $this->record->email }}"
                                        class="hover:text-blue-600">{{ $this->record->email }}</a>
                                @else
                                    <span style="color: #9ca3af;">Chưa có</span>
                                @endif
                            </p>
                        </div>
                        {{-- Birthday Card --}}
                        <div style="background: #f8fafc; border-radius: 12px; padding: 16px;"
                            class="dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                            <div class="flex items-center gap-3 mb-2">
                                <div style="width: 32px; height: 32px; background: #f3e8ff; border-radius: 8px; display: flex; align-items: center; justify-content: center;"
                                    class="dark:bg-purple-900/30">
                                    <svg style="color: #9333ea; width: 16px; height: 16px;" class="dark:text-purple-400"
                                        fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z"
                                            clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <span
                                    style="color: #6b7280; font-size: 11px; text-transform: uppercase; font-weight: 500;">Ngày
                                    sinh</span>
                            </div>
                            <p style="color: #111827; font-weight: 600; font-size: 14px;" class="dark:text-white">
                                @if($this->record->birthday)
                                    {{ \Carbon\Carbon::parse($this->record->birthday)->format('d/m/Y') }}
                                    <span
                                        style="color: #6b7280; font-weight: 400; font-size: 12px; margin-left: 4px;">({{ \Carbon\Carbon::parse($this->record->birthday)->age }}
                                        tuổi)</span>
                                @else
                                    <span style="color: #9ca3af;">Chưa có</span>
                                @endif
                            </p>
                        </div>
                        {{-- Branch Card --}}
                        <div style="background: #f8fafc; border-radius: 12px; padding: 16px;"
                            class="dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                            <div class="flex items-center gap-3 mb-2">
                                <div style="width: 32px; height: 32px; background: #fef3c7; border-radius: 8px; display: flex; align-items: center; justify-content: center;"
                                    class="dark:bg-amber-900/30">
                                    <svg style="color: #d97706; width: 16px; height: 16px;" class="dark:text-amber-400"
                                        fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a1 1 0 110 2h-3a1 1 0 01-1-1v-2a1 1 0 00-1-1H9a1 1 0 00-1 1v2a1 1 0 01-1 1H4a1 1 0 110-2V4z"
                                            clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <span
                                    style="color: #6b7280; font-size: 11px; text-transform: uppercase; font-weight: 500;">Chi
                                    nhánh</span>
                            </div>
                            <p style="color: #2563eb; font-weight: 600; font-size: 14px;" class="dark:text-blue-400">
                                {{ $this->record->branch?->name ?? 'Chưa phân bổ' }}
                            </p>
                        </div>
                    </div>
                    @if($this->record->address)
                        {{-- Address Card --}}
                        <div style="background: #f8fafc; border-radius: 12px; padding: 16px; margin-top: 16px;"
                            class="dark:bg-gray-800">
                            <div class="flex items-center gap-3 mb-2">
                                <div style="width: 32px; height: 32px; background: #fee2e2; border-radius: 8px; display: flex; align-items: center; justify-content: center;"
                                    class="dark:bg-red-900/30">
                                    <svg style="color: #dc2626; width: 16px; height: 16px;" class="dark:text-red-400"
                                        fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z"
                                            clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <span
                                    style="color: #6b7280; font-size: 11px; text-transform: uppercase; font-weight: 500;">Địa
                                    chỉ</span>
                            </div>
                            <p style="color: #111827; font-weight: 500; font-size: 14px;" class="dark:text-white">
                                {{ $this->record->address }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Tabs Navigation --}}
        <div class="mb-8">
            <div
                class="bg-white dark:bg-gray-900 rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 px-4 p-2">
                @php
                    $tpTotal = $this->record->treatmentPlans()->count();
                    $tpActive = $this->record->treatmentPlans()->whereIn('status', ['approved', 'in_progress'])->count();
                    $invTotal = $this->record->invoices()->count();
                    $unpaidInvoices = $this->record->invoices()->whereIn('status', ['issued', 'partial', 'overdue'])->count();
                    $totalOwed = (float) $this->record->invoices()->whereIn('status', ['issued', 'partial', 'overdue'])->sum(\DB::raw('total_amount - paid_amount'));
                    $apptTotal = $this->record->appointments()->count();
                    $upcomingAppointments = $this->record->appointments()->where('date', '>', now())->whereIn('status', ['scheduled', 'confirmed'])->count();
                    $notesCount = $this->record->notes()->count();
                    $clinicalNotesCount = $this->record->clinicalNotes()->count();
                    $photosCount = $this->record->photos()->count();
                @endphp
                <nav class="flex space-x-2 bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 rounded-xl p-2 mt-5 mb-5"
                    aria-label="Tabs"
                    style="padding: 10px; margin: 15px 0 15px 0; border-radius: 12px; background: white; box-shadow: 0 1px 2px rgba(0,0,0,0.05); border: 1px solid #e5e7eb;">
                    @php
                        $tabs = [
                            ['id' => 'overview', 'label' => 'Tổng quan', 'count' => null],
                            ['id' => 'exam-treatment', 'label' => 'Khám & Điều trị', 'count' => $clinicalNotesCount + $tpTotal],
                            ['id' => 'invoices', 'label' => 'Hóa đơn', 'count' => $invTotal],
                            ['id' => 'appointments', 'label' => 'Lịch hẹn', 'count' => $upcomingAppointments],
                            ['id' => 'notes', 'label' => 'Ghi chú', 'count' => $notesCount],
                            ['id' => 'clinical-notes', 'label' => 'Khám lâm sàng', 'count' => $clinicalNotesCount],
                            ['id' => 'photos', 'label' => 'Thư viện ảnh', 'count' => $photosCount],
                        ];
                    @endphp

                    @foreach($tabs as $tab)
                        <button wire:click="setActiveTab('{{ $tab['id'] }}')" @class([
                            'px-4 py-2.5 text-sm font-semibold rounded-lg transition-all duration-200 flex items-center gap-2',
                            'bg-primary-50 text-primary-600 dark:bg-primary-900/10 dark:text-primary-400' => $activeTab === $tab['id'],
                            'text-gray-500 hover:text-gray-700 hover:bg-gray-50 dark:text-gray-400 dark:hover:text-gray-300 dark:hover:bg-gray-800' => $activeTab !== $tab['id'],
                        ])
                            style="{{ $activeTab === $tab['id'] ? 'background-color: #eff6ff; color: #2563eb;' : 'color: #6b7280;' }}">
                            {{ $tab['label'] }}
                            @if($tab['count'] !== null)
                                <span class="rounded-full px-5 py-0.5 text-sm font-bold"
                                    style="{{ $activeTab === $tab['id'] ? 'background-color: #dbeafe; color: #1e40af;' : 'background-color: #f3f4f6; color: #4b5563;' }}">
                                    {{ $tab['count'] }}
                                </span>
                            @endif
                        </button>
                    @endforeach
                </nav>
            </div>


            {{-- Tab Content - Load all at once, show/hide with CSS --}}
            <div>
                {{-- Overview Tab --}}
                <div x-show="activeTab === 'overview'" x-cloak class="space-y-6">
                    @if($this->record)
                        {{-- Stats grid (concise) --}}
                        <div>
                            @livewire(\App\Filament\Resources\Patients\Widgets\PatientOverviewWidget::class, ['record' => $this->record], key('patient-' . $this->record->id . '-overview'))
                        </div>

                        {{-- Activity timeline (collapsible, full width) --}}
                        <div x-data="{open:false}" style="margin-top: 34px;">
                            <button type="button" @click="open=!open"
                                style="width: 100%; display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; font-size: 15px; font-weight: 600; border-radius: 12px; background: white; border: 1px solid #e5e7eb; box-shadow: 0 1px 3px rgba(0,0,0,0.05); cursor: pointer; transition: all 0.2s;"
                                class="dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700">
                                <span style="display: flex; align-items: center; gap: 10px; color: #111827;"
                                    class="dark:text-white">
                                    <svg style="width: 20px; height: 20px; color: #6b7280;" fill="none"
                                        stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    Lịch sử hoạt động
                                </span>
                                <svg x-show="!open"
                                    style="width: 20px; height: 20px; color: #6b7280; transition: transform 0.2s;"
                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 9l-7 7-7-7" />
                                </svg>
                                <svg x-show="open"
                                    style="width: 20px; height: 20px; color: #6b7280; transition: transform 0.2s;"
                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M5 15l7-7 7 7" />
                                </svg>
                            </button>
                            <div x-show="open" x-cloak style="margin-top: 16px;">
                                @livewire(\App\Filament\Resources\Patients\Widgets\PatientActivityTimelineWidget::class, ['record' => $this->record], key('patient-' . $this->record->id . '-timeline'))
                            </div>
                        </div>
                    @else
                        <div class="text-center py-12">
                            <p class="text-gray-500">Không thể tải dữ liệu bệnh nhân</p>
                        </div>
                    @endif
                </div>

                {{-- Exam & Treatment Tab --}}
                <div x-show="activeTab === 'exam-treatment'" x-cloak
                    wire:key="patient-{{ $this->record->id }}-exam-treatment">
                    <div class="space-y-4">
                        {{-- Header with Add Button --}}
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Khám & Điều trị</h3>
                        </div>

                        {{-- Accordion Sections --}}
                        <div x-data="{ openSection: 'general' }" class="space-y-3">
                            @livewire('patient-exam-form', ['patient' => $this->record])

                            {{-- 3. Chẩn đoán và điều trị --}}
                            <div
                                class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm">
                                <button type="button"
                                    @click="openSection = openSection === 'diagnosis' ? '' : 'diagnosis'"
                                    class="w-full flex justify-between items-center px-5 py-4 text-left hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                    <span class="font-semibold text-gray-800 dark:text-white flex items-center gap-2">
                                        <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                        </svg>
                                        Chẩn đoán và điều trị
                                    </span>
                                    <svg class="w-5 h-5 text-gray-400 transition-transform"
                                        :class="openSection === 'diagnosis' ? 'rotate-180' : ''" fill="none"
                                        stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                                <div x-show="openSection === 'diagnosis'" x-collapse
                                    class="border-t border-gray-100 dark:border-gray-700">
                                    <div class="p-5">
                                        <p class="text-sm text-gray-500 mb-4 italic">Click vào răng để thêm tình trạng.
                                            Mã tình trạng sẽ hiển thị trên răng.</p>
                                        @livewire(\App\Filament\Resources\Patients\RelationManagers\ClinicalNotesRelationManager::class, [
                                            'ownerRecord' => $this->record,
                                            'pageClass' => static::class,
                                        ])
                                    </div>
                                </div>
                            </div>

                            {{-- 4. Kế hoạch điều trị --}}
                            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm">
                                <button type="button" @click="openSection = openSection === 'treatment-plan' ? '' : 'treatment-plan'" 
                                    class="w-full flex justify-between items-center px-5 py-4 text-left hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                    <span class="font-semibold text-gray-800 dark:text-white flex items-center gap-2">
                                        <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                                        </svg>
                                        Kế hoạch điều trị ({{ $tpTotal }})
                                    </span>
                                    <svg class="w-5 h-5 text-gray-400 transition-transform" :class="openSection === 'treatment-plan' ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </button>
                                <div x-show="openSection === 'treatment-plan'" x-collapse class="border-t border-gray-100 dark:border-gray-700">
                                    <div class="p-5">
                                        @livewire(\App\Filament\Resources\Patients\RelationManagers\TreatmentPlansRelationManager::class, [
                                            'ownerRecord' => $this->record,
                                            'pageClass' => static::class,
                                        ])
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            {{-- Invoices Tab --}}
                
       <div x-show="activeTab === 'invoices'" x-cloak wire:key="patient-{{ $this->record->id }}-invoices">
            @livewire(\App\Filament\Resources\Patients\RelationManagers\InvoicesRelationManager::class, [
                'ownerRecord' => $this->record,
                'pageClass' => static::class,
            ])
                </div>
         
                               {{-- Appointments Tab --}}
                <div x-show="activeTab === 'appointments'" x-cloak wire:key="patient-{{ $this->record->id }}-appointments">
                            @livewire(\App\Filament\Resources\Patients\RelationManagers\AppointmentsRelationManager::class, [
                                'ownerRecord' => $this->record,
                                'pageClass' => static::class,
                            ])
    
                               </div>
    
                            {{-- Notes Tab --}}
                <div x-show="activeTab === 'notes'" x-cloak wire:key="patient-{{ $this->record->id }}-notes">
                    @livewire(\App\Filament\Resources\Patients\Relations\PatientNotesRelationManager::class, [
                        'ownerRecord' => $this->record,
                        'pageClass' => static::class,
                    ])
        </div>

        {{-- Clinical Notes Tab --}}
        <div x-show="activeTab === 'clinical-notes'" x-cloak wire:key="patient-{{ $this->record->id }}-clinical-notes">
            @livewire(\App\Filament\Resources\Patients\RelationManagers\ClinicalNotesRelationManager::class, [
                'ownerRecord' => $this->record,
                'pageClass' => static::class,
            ])
        </div>

        {{-- Photos Tab --}}
        <div x-show="activeTab === 'photos'" x-cloak wire:key="patient-{{ $this->record->id }}-photos">
            @livewire(\App\Filament\Resources\Patients\RelationManagers\PatientPhotosRelationManager::class, [
                'ownerRecord' => $this->record,
                'pageClass' => static::class,
            ])
        </div>
    </div>

    </div>
</x-filament-panels::page>
