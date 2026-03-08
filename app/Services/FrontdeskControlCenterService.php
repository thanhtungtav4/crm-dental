<?php

namespace App\Services;

use App\Filament\Pages\CustomerCare;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\Appointments\Pages\CalendarAppointments;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Patients\PatientResource;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Note;
use App\Support\BranchAccess;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class FrontdeskControlCenterService
{
    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $leadPipeline = $this->buildLeadPipelineSection();
        $appointmentHandoff = $this->buildAppointmentHandoffSection();
        $careQueue = $this->buildCareQueueSection();

        return [
            'overview_cards' => $this->buildOverviewCards($leadPipeline, $appointmentHandoff, $careQueue),
            'quick_links' => $this->quickLinks(),
            'sections' => [
                $leadPipeline,
                $appointmentHandoff,
                $careQueue,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $leadPipeline
     * @param  array<string, mixed>  $appointmentHandoff
     * @param  array<string, mixed>  $careQueue
     * @return array<int, array<string, mixed>>
     */
    protected function buildOverviewCards(array $leadPipeline, array $appointmentHandoff, array $careQueue): array
    {
        return [
            [
                'title' => 'Lead cần chạm',
                'value' => $leadPipeline['metrics']['due_today']['value'],
                'status' => $leadPipeline['metrics']['due_today']['label'],
                'tone' => $leadPipeline['metrics']['due_today']['tone'],
                'description' => 'Lead tới hạn follow-up trong ngày hoặc đã quá hạn cần xử lý ngay.',
                'meta' => [
                    'Mở pipeline '.$leadPipeline['metrics']['open']['value'],
                ],
            ],
            [
                'title' => 'Lịch 48h tới',
                'value' => $appointmentHandoff['metrics']['next_48h']['value'],
                'status' => $appointmentHandoff['metrics']['next_48h']['label'],
                'tone' => $appointmentHandoff['metrics']['next_48h']['tone'],
                'description' => 'Booking sắp tới cần xác nhận, chuẩn bị bác sĩ và ghế.',
                'meta' => [
                    'Chưa xác nhận '.$appointmentHandoff['metrics']['needs_confirmation']['value'],
                ],
            ],
            [
                'title' => 'Queue CSKH quá hạn',
                'value' => $careQueue['metrics']['overdue']['value'],
                'status' => $careQueue['metrics']['overdue']['label'],
                'tone' => $careQueue['metrics']['overdue']['tone'],
                'description' => 'Ticket CSKH mở nhưng đã trễ SLA so với thời điểm chăm sóc.',
                'meta' => [
                    'Mở queue '.$careQueue['metrics']['open']['value'],
                ],
            ],
            [
                'title' => 'No-show cần gọi lại',
                'value' => $careQueue['metrics']['no_show_recovery']['value'],
                'status' => $careQueue['metrics']['no_show_recovery']['label'],
                'tone' => $careQueue['metrics']['no_show_recovery']['tone'],
                'description' => 'Ưu tiên xử lý recovery cho lịch đã bỏ hẹn để tránh rơi khách.',
                'meta' => [
                    'Hôm nay '.$careQueue['metrics']['due_today']['value'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildLeadPipelineSection(): array
    {
        $openStatuses = ['lead', 'contacted', 'confirmed'];
        $baseQuery = $this->leadPipelineQuery();

        $metrics = [
            'open' => $this->metric(
                label: 'Lead đang mở',
                value: (int) (clone $baseQuery)->count(),
                tone: 'info',
                description: 'Tất cả lead chưa convert thành bệnh nhân trong scope hiện tại.',
            ),
            'due_today' => $this->metric(
                label: 'Cần chạm hôm nay',
                value: (int) (clone $baseQuery)
                    ->whereNotNull('next_follow_up_at')
                    ->where('next_follow_up_at', '<=', now()->endOfDay())
                    ->count(),
                tone: 'warning',
                description: 'Lead có lịch follow-up đến hạn trong ngày.',
            ),
            'ready_for_intake' => $this->metric(
                label: 'Sẵn sàng intake',
                value: (int) (clone $baseQuery)
                    ->whereIn('status', ['confirmed'])
                    ->count(),
                tone: 'success',
                description: 'Lead đã xác nhận, có thể kéo sang create patient hoặc create appointment.',
            ),
            'unassigned' => $this->metric(
                label: 'Chưa phân công',
                value: (int) (clone $baseQuery)
                    ->whereNull('assigned_to')
                    ->count(),
                tone: 'danger',
                description: 'Lead chưa có người chịu trách nhiệm follow-up.',
            ),
        ];

        $rows = (clone $baseQuery)
            ->with(['branch:id,name', 'assignee:id,name'])
            ->whereIn('status', $openStatuses)
            ->orderByRaw('CASE WHEN next_follow_up_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('next_follow_up_at')
            ->orderByDesc('updated_at')
            ->limit(6)
            ->get()
            ->map(function (Customer $customer): array {
                return [
                    'title' => $customer->full_name,
                    'subtitle' => (string) ($customer->source_detail ?: 'Lead chưa có nguồn chi tiết'),
                    'badge' => $this->customerStatusLabel($customer->status),
                    'tone' => $this->customerStatusTone($customer->status),
                    'meta' => [
                        ['label' => 'Theo dõi', 'value' => $this->formatDateTime($customer->next_follow_up_at)],
                        ['label' => 'Chi nhánh', 'value' => (string) ($customer->branch?->name ?? 'Không xác định')],
                        ['label' => 'Phụ trách', 'value' => (string) ($customer->assignee?->name ?? 'Chưa phân công')],
                    ],
                ];
            })
            ->values()
            ->all();

        return [
            'title' => 'Lead pipeline',
            'description' => 'Nhìn nhanh trạng thái lead đang mở để phân công intake và ưu tiên follow-up.',
            'metrics' => $metrics,
            'rows' => $rows,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildAppointmentHandoffSection(): array
    {
        $baseQuery = $this->appointmentQuery();
        $upcomingWindow = [
            now()->startOfMinute(),
            now()->copy()->addHours(48),
        ];

        $metrics = [
            'today' => $this->metric(
                label: 'Hôm nay',
                value: (int) (clone $baseQuery)
                    ->whereBetween('date', [now()->startOfDay(), now()->endOfDay()])
                    ->whereIn('status', Appointment::statusesForQuery([
                        Appointment::STATUS_SCHEDULED,
                        Appointment::STATUS_CONFIRMED,
                        Appointment::STATUS_IN_PROGRESS,
                    ]))
                    ->count(),
                tone: 'info',
                description: 'Lịch hẹn còn hoạt động trong ngày hôm nay.',
            ),
            'next_48h' => $this->metric(
                label: '48h tới',
                value: (int) (clone $baseQuery)
                    ->whereBetween('date', $upcomingWindow)
                    ->whereIn('status', Appointment::statusesForQuery([
                        Appointment::STATUS_SCHEDULED,
                        Appointment::STATUS_CONFIRMED,
                    ]))
                    ->count(),
                tone: 'primary',
                description: 'Booking sắp tới cần xác nhận và điều phối ghế/bác sĩ.',
            ),
            'needs_confirmation' => $this->metric(
                label: 'Chưa xác nhận',
                value: (int) (clone $baseQuery)
                    ->whereBetween('date', $upcomingWindow)
                    ->whereIn('status', Appointment::statusesForQuery([Appointment::STATUS_SCHEDULED]))
                    ->whereNull('confirmed_at')
                    ->count(),
                tone: 'warning',
                description: 'Lịch đã đặt nhưng chưa xác nhận lại với bệnh nhân.',
            ),
            'operational_risk' => $this->metric(
                label: 'Cần chú ý',
                value: (int) (clone $baseQuery)
                    ->whereBetween('date', [now()->startOfMinute(), now()->copy()->addHours(72)])
                    ->where(function (Builder $query): void {
                        $query
                            ->where('is_overbooked', true)
                            ->orWhere('is_walk_in', true)
                            ->orWhere('is_emergency', true);
                    })
                    ->count(),
                tone: 'danger',
                description: 'Lịch walk-in, emergency hoặc overbook cần điều phối sát.',
            ),
        ];

        $rows = (clone $baseQuery)
            ->with(['patient:id,full_name', 'customer:id,full_name', 'doctor:id,name', 'branch:id,name'])
            ->whereBetween('date', [now()->startOfMinute(), now()->copy()->addHours(72)])
            ->whereIn('status', Appointment::statusesForQuery([
                Appointment::STATUS_SCHEDULED,
                Appointment::STATUS_CONFIRMED,
                Appointment::STATUS_IN_PROGRESS,
            ]))
            ->orderBy('date')
            ->limit(6)
            ->get()
            ->map(function (Appointment $appointment): array {
                $patientName = $appointment->patient?->full_name ?: $appointment->customer?->full_name ?: 'Chưa rõ bệnh nhân';

                return [
                    'title' => $patientName,
                    'subtitle' => (string) ($appointment->chief_complaint ?: Appointment::statusLabel($appointment->status)),
                    'badge' => Appointment::statusLabel($appointment->status),
                    'tone' => $this->normalizeTone(Appointment::statusColor($appointment->status)),
                    'meta' => [
                        ['label' => 'Giờ hẹn', 'value' => $this->formatDateTime($appointment->date)],
                        ['label' => 'Bác sĩ', 'value' => (string) ($appointment->doctor?->name ?? 'Chưa gán')],
                        ['label' => 'Chi nhánh', 'value' => (string) ($appointment->branch?->name ?? 'Không xác định')],
                    ],
                ];
            })
            ->values()
            ->all();

        return [
            'title' => 'Lịch hẹn cần bàn giao',
            'description' => 'Tập trung vào booking 48-72 giờ tới để điều phối xác nhận, ghế và bác sĩ.',
            'metrics' => $metrics,
            'rows' => $rows,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildCareQueueSection(): array
    {
        $baseQuery = $this->careQueueQuery();

        $metrics = [
            'open' => $this->metric(
                label: 'Mở queue',
                value: (int) (clone $baseQuery)->count(),
                tone: 'info',
                description: 'Ticket CSKH đang mở theo workflow canonical.',
            ),
            'overdue' => $this->metric(
                label: 'Quá hạn',
                value: (int) (clone $baseQuery)
                    ->whereNotNull('care_at')
                    ->where('care_at', '<', now())
                    ->count(),
                tone: 'danger',
                description: 'Ticket có care_at đã trễ so với hiện tại.',
            ),
            'due_today' => $this->metric(
                label: 'Trong ngày',
                value: (int) (clone $baseQuery)
                    ->whereDate('care_at', now()->toDateString())
                    ->count(),
                tone: 'warning',
                description: 'Ticket cần xử lý trong ngày làm việc hiện tại.',
            ),
            'no_show_recovery' => $this->metric(
                label: 'No-show recovery',
                value: (int) (clone $baseQuery)
                    ->where('care_type', 'no_show_recovery')
                    ->count(),
                tone: 'warning',
                description: 'Ticket recovery cho lịch no-show đang mở.',
            ),
        ];

        $rows = (clone $baseQuery)
            ->with(['patient:id,full_name', 'customer:id,full_name', 'user:id,name', 'branch:id,name'])
            ->orderByRaw('CASE WHEN care_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('care_at')
            ->limit(6)
            ->get()
            ->map(function (Note $note): array {
                $title = $note->patient?->full_name ?: $note->customer?->full_name ?: 'Ticket chưa gắn hồ sơ';

                return [
                    'title' => $title,
                    'subtitle' => $this->careTypeLabel($note->care_type),
                    'badge' => Note::careStatusLabel($note->care_status),
                    'tone' => $this->normalizeTone(Note::careStatusColor($note->care_status)),
                    'meta' => [
                        ['label' => 'Hẹn chăm sóc', 'value' => $this->formatDateTime($note->care_at)],
                        ['label' => 'Phụ trách', 'value' => (string) ($note->user?->name ?? 'Chưa phân công')],
                        ['label' => 'Chi nhánh', 'value' => (string) ($note->branch?->name ?? 'Không xác định')],
                    ],
                ];
            })
            ->values()
            ->all();

        return [
            'title' => 'Queue CSKH',
            'description' => 'Theo dõi ticket đang mở, no-show recovery và các đầu việc follow-up cần đóng trong ngày.',
            'metrics' => $metrics,
            'rows' => $rows,
        ];
    }

    protected function leadPipelineQuery(): Builder
    {
        return BranchAccess::scopeQueryByAccessibleBranches(
            Customer::query()
                ->whereIn('status', ['lead', 'contacted', 'confirmed'])
                ->whereDoesntHave('patient'),
            'branch_id',
        );
    }

    protected function appointmentQuery(): Builder
    {
        return BranchAccess::scopeQueryByAccessibleBranches(
            Appointment::query(),
            'branch_id',
        );
    }

    protected function careQueueQuery(): Builder
    {
        return BranchAccess::scopeQueryByAccessibleBranches(
            Note::query()
                ->whereIn('care_status', Note::statusesForQuery(Note::activeCareStatuses())),
            'branch_id',
        );
    }

    /**
     * @return array<int, array<string, string>>
     */
    protected function quickLinks(): array
    {
        return [
            [
                'label' => 'Khách hàng',
                'description' => 'Danh sách lead và thao tác convert/intake.',
                'url' => CustomerResource::getUrl('index'),
            ],
            [
                'label' => 'Bệnh nhân',
                'description' => 'Mở workspace bệnh nhân để tiếp tục intake hoặc giao bác sĩ.',
                'url' => PatientResource::getUrl('index'),
            ],
            [
                'label' => 'Lịch hẹn tổng',
                'description' => 'Calendar vận hành để xác nhận, dời lịch và nhìn booking theo chi nhánh.',
                'url' => CalendarAppointments::getUrl(),
            ],
            [
                'label' => 'Queue ưu tiên',
                'description' => 'Mở thẳng page CSKH với queue cần xử lý trước.',
                'url' => CustomerCare::getUrl(['tab' => 'priority_queue']),
            ],
            [
                'label' => 'Tạo lịch hẹn',
                'description' => 'Vào form tạo lịch mới cho lead hoặc bệnh nhân có sẵn.',
                'url' => AppointmentResource::getUrl('create'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function metric(string $label, int $value, string $tone, string $description): array
    {
        return [
            'label' => $label,
            'value' => number_format($value),
            'raw_value' => $value,
            'tone' => $this->normalizeTone($tone),
            'description' => $description,
        ];
    }

    protected function customerStatusLabel(?string $status): string
    {
        return match (Str::lower((string) $status)) {
            'lead' => 'Lead mới',
            'contacted' => 'Đã liên hệ',
            'confirmed' => 'Đã xác nhận',
            'converted' => 'Đã chuyển đổi',
            default => 'Chưa xác định',
        };
    }

    protected function customerStatusTone(?string $status): string
    {
        return match (Str::lower((string) $status)) {
            'lead' => 'warning',
            'contacted' => 'info',
            'confirmed', 'converted' => 'success',
            default => 'gray',
        };
    }

    protected function careTypeLabel(?string $careType): string
    {
        return match ((string) $careType) {
            'no_show_recovery' => 'No-show recovery',
            'recall_recare' => 'Recall / re-care',
            'treatment_plan_follow_up' => 'Follow-up kế hoạch',
            'implant_followup' => 'Follow-up implant',
            'orthodontic_followup' => 'Follow-up chỉnh nha',
            'appointment_reminder' => 'Nhắc lịch hẹn',
            'medication_reminder' => 'Nhắc uống thuốc',
            'post_treatment_follow_up' => 'Hỏi thăm sau điều trị',
            'birthday_care' => 'Chăm sóc sinh nhật',
            'web_lead_followup' => 'Follow-up web lead',
            default => Str::headline((string) $careType),
        };
    }

    protected function formatDateTime(CarbonInterface|string|null $value): string
    {
        if (! $value instanceof CarbonInterface) {
            return 'Chưa lên lịch';
        }

        return $value->format('d/m/Y H:i');
    }

    protected function normalizeTone(?string $tone): string
    {
        return match ($tone) {
            'primary' => 'info',
            'gray' => 'gray',
            'success', 'warning', 'danger', 'info' => $tone,
            default => 'info',
        };
    }
}
