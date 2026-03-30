<?php

use App\Filament\Resources\Patients\Pages\ViewPatient;
use App\Models\Patient;
use App\Models\PlanItem;
use App\Models\TreatmentPlan;
use App\Models\TreatmentSession;
use App\Models\User;
use App\Services\PatientOverviewReadModelService;

it('builds treatment progress totals by day and by session for exam treatment tab', function (): void {
    $doctor = User::factory()->create();
    $doctor->assignRole('Doctor');

    $patient = Patient::factory()->create();

    $plan = TreatmentPlan::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $patient->first_branch_id,
        'doctor_id' => $doctor->id,
        'title' => 'Kế hoạch tổng hợp tiến trình',
        'status' => TreatmentPlan::STATUS_APPROVED,
    ]);

    $firstPlanItem = PlanItem::query()->create([
        'treatment_plan_id' => $plan->id,
        'name' => 'Nạo vôi răng',
        'tooth_number' => '16',
        'quantity' => 1,
        'price' => 700000,
        'final_amount' => 700000,
        'approval_status' => PlanItem::APPROVAL_APPROVED,
    ]);

    $secondPlanItem = PlanItem::query()->create([
        'treatment_plan_id' => $plan->id,
        'name' => 'Trám răng',
        'tooth_number' => '26',
        'quantity' => 1,
        'price' => 500000,
        'final_amount' => 500000,
        'approval_status' => PlanItem::APPROVAL_APPROVED,
    ]);

    $thirdPlanItem = PlanItem::query()->create([
        'treatment_plan_id' => $plan->id,
        'name' => 'Điều trị tuỷ',
        'tooth_number' => '36',
        'quantity' => 1,
        'price' => 800000,
        'final_amount' => 800000,
        'approval_status' => PlanItem::APPROVAL_APPROVED,
    ]);

    TreatmentSession::query()->create([
        'treatment_plan_id' => $plan->id,
        'plan_item_id' => $firstPlanItem->id,
        'doctor_id' => $doctor->id,
        'performed_at' => '2026-03-01 09:00:00',
        'status' => 'done',
    ]);

    TreatmentSession::query()->create([
        'treatment_plan_id' => $plan->id,
        'plan_item_id' => $secondPlanItem->id,
        'doctor_id' => $doctor->id,
        'performed_at' => '2026-03-01 15:00:00',
        'status' => 'done',
    ]);

    TreatmentSession::query()->create([
        'treatment_plan_id' => $plan->id,
        'plan_item_id' => $thirdPlanItem->id,
        'doctor_id' => $doctor->id,
        'performed_at' => '2026-03-02 10:00:00',
        'status' => 'done',
    ]);

    $page = new class extends ViewPatient
    {
        public function forceRecord(Patient $patient): void
        {
            $this->record = $patient;
        }
    };

    $page->forceRecord($patient->fresh());

    $service = app(PatientOverviewReadModelService::class);
    $serviceDaySummaries = $service->treatmentProgressDaySummaries($patient->fresh());
    $serviceSessions = $service->treatmentProgress($patient->fresh());
    $summary = $service->treatmentProgressSummary($serviceSessions, $serviceDaySummaries);
    $panel = $service->treatmentProgressPanelPayload($patient->fresh(), $doctor, route('filament.admin.resources.patients.view', [
        'record' => $patient,
        'tab' => 'exam-treatment',
    ]), $serviceSessions, $serviceDaySummaries);

    $daySummaries = $page->getTreatmentProgressDaySummariesProperty();
    $sessions = $page->getTreatmentProgressProperty();

    expect($serviceSessions->pluck('session_id')->all())->toBe($sessions->pluck('session_id')->all())
        ->and($serviceDaySummaries->pluck('progress_date')->all())->toBe($daySummaries->pluck('progress_date')->all())
        ->and($summary)->toMatchArray([
            'sessions_count' => 3,
            'days_count' => 2,
            'total_amount' => 2000000.0,
            'total_amount_formatted' => '2.000.000',
        ])
        ->and($panel)->toMatchArray([
            'section_title' => 'Tiến trình điều trị',
            'card_title' => 'Tiến trình điều trị',
            'sessions_count' => 3,
            'days_count' => 2,
            'has_day_summaries' => true,
            'summary_badge' => '2 ngày · 3 phiên',
            'total_amount_text' => 'Tổng chi phí phiên: 2.000.000đ',
            'empty_text' => 'Chưa có tiến trình điều trị cho bệnh nhân này.',
            'primary_action' => [
                'label' => 'Thêm ngày điều trị',
                'url' => route('filament.admin.resources.treatment-sessions.create', [
                    'patient_id' => $patient->id,
                    'return_url' => route('filament.admin.resources.patients.view', [
                        'record' => $patient,
                        'tab' => 'exam-treatment',
                    ]),
                ]),
                'button_class' => 'crm-btn-primary',
            ],
        ])
        ->and($sessions)->toHaveCount(3)
        ->and($sessions->first())->toMatchArray([
            'edit_action_label' => 'Chỉnh sửa phiên điều trị',
            'edit_action_placeholder' => '-',
        ])
        ->and($sessions->first()['edit_action'])->toMatchArray([
            'url' => $sessions->first()['edit_url'],
            'label' => 'Chỉnh sửa phiên điều trị',
            'icon' => 'heroicon-o-pencil-square',
            'button_class' => 'crm-table-icon-btn',
        ])
        ->and((int) ($page->getTreatmentProgressPanelProperty()['sessions_count'] ?? 0))->toBe(3)
        ->and($daySummaries)->toHaveCount(2)
        ->and((int) ($page->getTreatmentProgressPanelProperty()['days_count'] ?? 0))->toBe(2)
        ->and((float) ($page->getTreatmentProgressPanelProperty()['total_amount'] ?? 0))->toEqualWithDelta(2000000.0, 0.01)
        ->and((string) ($page->getTreatmentProgressPanelProperty()['total_amount_formatted'] ?? '0'))->toBe('2.000.000')
        ->and((float) ($daySummaries->first()['day_total_amount'] ?? 0))->toEqualWithDelta(800000.0, 0.01)
        ->and((int) ($daySummaries->first()['sessions_count'] ?? 0))->toBe(1)
        ->and((float) ($daySummaries->last()['day_total_amount'] ?? 0))->toEqualWithDelta(1200000.0, 0.01)
        ->and((int) ($daySummaries->last()['sessions_count'] ?? 0))->toBe(2);
});
