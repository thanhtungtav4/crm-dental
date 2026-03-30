<?php

namespace App\Services;

use App\Filament\Resources\FactoryOrders\FactoryOrderResource;
use App\Filament\Resources\MaterialIssueNotes\MaterialIssueNoteResource;
use App\Filament\Resources\PatientMedicalRecords\PatientMedicalRecordResource;
use App\Filament\Resources\TreatmentMaterials\TreatmentMaterialResource;
use App\Models\Appointment;
use App\Models\FactoryOrder;
use App\Models\Invoice;
use App\Models\MaterialIssueNote;
use App\Models\Note;
use App\Models\Patient;
use App\Models\PatientMedicalRecord;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\TreatmentMaterial;
use App\Models\TreatmentPlan;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PatientOverviewReadModelService
{
    /**
     * @return array{
     *     avatar_initials:string,
     *     full_name:string,
     *     gender_label:?string,
     *     gender_badge_class:?string,
     *     patient_code:?string,
     *     patient_code_copy_label:?string,
     *     patient_code_copy_action_label:?string,
     *     phone:?string,
     *     phone_href:?string,
     *     phone_copy_label:?string,
     *     phone_copy_action_label:?string
     * }
     */
    public function identityHeaderPayload(Patient $patient): array
    {
        $patientCode = filled($patient->patient_code) ? (string) $patient->patient_code : null;
        $phone = filled($patient->phone) ? (string) $patient->phone : null;

        return [
            'avatar_initials' => $this->patientInitials((string) $patient->full_name),
            'full_name' => (string) $patient->full_name,
            'gender_label' => $this->patientGenderLabel($patient->gender),
            'gender_badge_class' => $this->patientGenderBadgeClass($patient->gender),
            'patient_code' => $patientCode,
            'patient_code_copy_label' => $patientCode !== null ? 'Mã bệnh nhân' : null,
            'patient_code_copy_action_label' => $patientCode !== null ? 'Sao chép mã bệnh nhân' : null,
            'phone' => $phone,
            'phone_href' => $phone !== null ? 'tel:'.$phone : null,
            'phone_copy_label' => $phone !== null ? 'Số điện thoại' : null,
            'phone_copy_action_label' => $phone !== null ? 'Sao chép số điện thoại' : null,
        ];
    }

    /**
     * @return array{
     *     phone:?string,
     *     phone_href:?string,
     *     email:?string,
     *     email_href:?string,
     *     birthday_label:?string,
     *     age_label:?string,
     *     branch_name:string,
     *     address:?string,
     *     cards:array<int, array{
     *         key:string,
     *         label:string,
     *         icon:string,
     *         card_class:string,
     *         value:string,
     *         href:?string,
     *         copy_value:?string,
     *         copy_label:?string,
     *         copy_action_label:?string,
     *         meta:?string,
     *         is_muted:bool,
     *         is_truncate:bool,
     *         title:?string
     *     }>,
     *     address_card:?array{
     *         label:string,
     *         icon:string,
     *         value:string
     *     }
     * }
     */
    public function basicInfoGridPayload(Patient $patient): array
    {
        $birthday = filled($patient->birthday) ? Carbon::parse($patient->birthday) : null;
        $phone = filled($patient->phone) ? (string) $patient->phone : null;
        $email = filled($patient->email) ? (string) $patient->email : null;
        $address = filled($patient->address) ? (string) $patient->address : null;
        $birthdayLabel = $birthday?->format('d/m/Y');
        $ageLabel = $birthday?->age !== null ? sprintf('(%d tuổi)', $birthday->age) : null;
        $branchName = $patient->branch?->name ?? 'Chưa phân bổ';

        return [
            'phone' => $phone,
            'phone_href' => $phone !== null ? 'tel:'.$phone : null,
            'email' => $email,
            'email_href' => $email !== null ? 'mailto:'.$email : null,
            'birthday_label' => $birthdayLabel,
            'age_label' => $ageLabel,
            'branch_name' => $branchName,
            'address' => $address,
            'cards' => [
                [
                    'key' => 'phone',
                    'label' => 'Điện thoại',
                    'icon' => 'heroicon-o-phone',
                    'card_class' => 'is-phone',
                    'value' => $phone ?? 'Chưa có',
                    'href' => $phone !== null ? 'tel:'.$phone : null,
                    'copy_value' => $phone,
                    'copy_label' => $phone !== null ? 'Số điện thoại' : null,
                    'copy_action_label' => $phone !== null ? 'Sao chép số điện thoại' : null,
                    'meta' => null,
                    'is_muted' => $phone === null,
                    'is_truncate' => false,
                    'title' => null,
                ],
                [
                    'key' => 'email',
                    'label' => 'Email',
                    'icon' => 'heroicon-o-envelope',
                    'card_class' => 'is-email',
                    'value' => $email ?? 'Chưa có',
                    'href' => $email !== null ? 'mailto:'.$email : null,
                    'copy_value' => null,
                    'copy_label' => null,
                    'copy_action_label' => null,
                    'meta' => null,
                    'is_muted' => $email === null,
                    'is_truncate' => true,
                    'title' => $email,
                ],
                [
                    'key' => 'birthday',
                    'label' => 'Ngày sinh',
                    'icon' => 'heroicon-o-calendar-days',
                    'card_class' => 'is-birthday',
                    'value' => $birthdayLabel ?? 'Chưa có',
                    'href' => null,
                    'copy_value' => null,
                    'copy_label' => null,
                    'copy_action_label' => null,
                    'meta' => $ageLabel,
                    'is_muted' => $birthdayLabel === null,
                    'is_truncate' => false,
                    'title' => null,
                ],
                [
                    'key' => 'branch',
                    'label' => 'Chi nhánh',
                    'icon' => 'heroicon-o-building-office-2',
                    'card_class' => 'is-branch',
                    'value' => $branchName,
                    'href' => null,
                    'copy_value' => null,
                    'copy_label' => null,
                    'copy_action_label' => null,
                    'meta' => null,
                    'is_muted' => false,
                    'is_truncate' => false,
                    'title' => null,
                ],
            ],
            'address_card' => $address !== null
                ? [
                    'label' => 'Địa chỉ',
                    'icon' => 'heroicon-o-map-pin',
                    'value' => $address,
                ]
                : null,
        ];
    }

    /**
     * @return array{
     *     contacts:array{
     *         title:string,
     *         description:string
     *     },
     *     activity_log:array{
     *         title:string,
     *         description:string,
     *         action:array{
     *             label:string,
     *             tab:string,
     *             button_class:string
     *         }
     *     },
     *     empty_state_text:string
     * }
     */
    public function basicInfoPanelsPayload(): array
    {
        return [
            'contacts' => [
                'title' => 'Người liên hệ',
                'description' => 'Tách danh sách người liên hệ để lễ tân/CSKH thao tác nhanh theo từng bệnh nhân.',
            ],
            'activity_log' => [
                'title' => 'Lịch sử thao tác',
                'description' => 'Xem timeline cập nhật ở tab chuyên biệt để tránh trùng lặp nội dung.',
                'action' => [
                    'label' => 'Mở lịch sử thao tác',
                    'tab' => 'activity-log',
                    'button_class' => 'crm-btn crm-btn-primary crm-btn-md',
                ],
            ],
            'empty_state_text' => 'Không thể tải dữ liệu bệnh nhân',
        ];
    }

    /**
     * @return array{
     *     treatment_plans_count:int,
     *     active_treatment_plans_count:int,
     *     invoices_count:int,
     *     unpaid_invoices_count:int,
     *     total_owed:float,
     *     appointments_count:int,
     *     upcoming_appointments_count:int,
     *     total_spent:float,
     *     total_paid:float,
     *     last_visit_at:?Carbon,
     *     next_appointment_at:?Carbon
     * }
     */
    public function overview(Patient $patient): array
    {
        $treatmentPlans = $patient->treatmentPlans()
            ->get(['id', 'status']);

        $invoices = $patient->invoices()
            ->get(['id', 'status', 'total_amount', 'paid_amount']);

        $appointments = $patient->appointments()
            ->orderBy('date')
            ->get(['id', 'date', 'status']);

        $activeTreatmentStatuses = [
            TreatmentPlan::STATUS_APPROVED,
            TreatmentPlan::STATUS_IN_PROGRESS,
        ];

        $unpaidInvoiceStatuses = [
            'issued',
            'partial',
            'overdue',
        ];

        $upcomingAppointments = $appointments
            ->filter(function (Appointment $appointment): bool {
                return $appointment->date !== null
                    && $appointment->date->isFuture()
                    && in_array(
                        Appointment::normalizeStatus($appointment->status),
                        Appointment::activeStatuses(),
                        true,
                    );
            })
            ->values();

        $lastVisit = $appointments
            ->filter(fn (Appointment $appointment): bool => in_array(
                Appointment::normalizeStatus($appointment->status),
                Appointment::statusesForQuery([Appointment::STATUS_COMPLETED]),
                true,
            ))
            ->sortByDesc(fn (Appointment $appointment): int => $appointment->date?->getTimestamp() ?? 0)
            ->first();

        return [
            'treatment_plans_count' => $treatmentPlans->count(),
            'active_treatment_plans_count' => $treatmentPlans
                ->whereIn('status', $activeTreatmentStatuses)
                ->count(),
            'invoices_count' => $invoices->count(),
            'unpaid_invoices_count' => $invoices
                ->whereIn('status', $unpaidInvoiceStatuses)
                ->count(),
            'total_owed' => round((float) $invoices
                ->whereIn('status', $unpaidInvoiceStatuses)
                ->sum(fn ($invoice): float => max(0, (float) $invoice->total_amount - (float) $invoice->paid_amount)), 2),
            'appointments_count' => $appointments->count(),
            'upcoming_appointments_count' => $upcomingAppointments->count(),
            'total_spent' => round((float) $invoices
                ->where('status', 'paid')
                ->sum('total_amount'), 2),
            'total_paid' => round((float) $invoices->sum('paid_amount'), 2),
            'last_visit_at' => $lastVisit?->date,
            'next_appointment_at' => $upcomingAppointments->first()?->date,
        ];
    }

    /**
     * @return array{
     *     total_treatment_amount:float,
     *     total_treatment_amount_formatted:string,
     *     total_discount_amount:float,
     *     total_discount_amount_formatted:string,
     *     must_pay_amount:float,
     *     must_pay_amount_formatted:string,
     *     net_collected_amount:float,
     *     net_collected_amount_formatted:string,
     *     remaining_amount:float,
     *     remaining_amount_formatted:string,
     *     balance_amount:float,
     *     balance_amount_formatted:string,
     *     balance_is_positive:bool,
     *     latest_invoice_id:?int,
     *     create_payment_url:string
     * }
     */
    public function paymentSummary(Patient $patient): array
    {
        $invoices = $patient->invoices()
            ->latest('created_at')
            ->get(['id', 'status', 'total_amount', 'discount_amount', 'paid_amount']);

        $payments = $patient->payments()
            ->get(['payments.id', 'payments.direction', 'payments.amount']);

        $totalTreatmentAmount = round((float) $invoices->sum('total_amount'), 2);
        $totalDiscountAmount = round((float) $invoices->sum('discount_amount'), 2);
        $mustPayAmount = max(0, $totalTreatmentAmount);
        $receiptAmount = round((float) $payments->where('direction', 'receipt')->sum('amount'), 2);
        $refundAmount = abs(round((float) $payments->where('direction', 'refund')->sum('amount'), 2));
        $netCollectedAmount = round($receiptAmount - $refundAmount, 2);
        $remainingAmount = max(0, round($mustPayAmount - $netCollectedAmount, 2));
        $balanceAmount = round($netCollectedAmount - $mustPayAmount, 2);

        $latestInvoiceId = $invoices
            ->firstWhere(fn ($invoice): bool => ! in_array($invoice->status, ['paid', 'cancelled'], true))
            ?->id;

        $latestInvoiceId ??= $invoices->first()?->id;

        return [
            'total_treatment_amount' => $totalTreatmentAmount,
            'total_treatment_amount_formatted' => $this->formatMoney($totalTreatmentAmount),
            'total_discount_amount' => $totalDiscountAmount,
            'total_discount_amount_formatted' => $this->formatMoney($totalDiscountAmount),
            'must_pay_amount' => $mustPayAmount,
            'must_pay_amount_formatted' => $this->formatMoney($mustPayAmount),
            'net_collected_amount' => $netCollectedAmount,
            'net_collected_amount_formatted' => $this->formatMoney($netCollectedAmount),
            'remaining_amount' => $remainingAmount,
            'remaining_amount_formatted' => $this->formatMoney($remainingAmount),
            'balance_amount' => $balanceAmount,
            'balance_amount_formatted' => $this->formatMoney($balanceAmount),
            'balance_is_positive' => $balanceAmount >= 0,
            'latest_invoice_id' => $latestInvoiceId,
            'create_payment_url' => route(
                'filament.admin.resources.payments.create',
                $latestInvoiceId ? ['invoice_id' => $latestInvoiceId] : []
            ),
        ];
    }

    /**
     * @return array{
     *     summary:array{
     *         total_treatment_amount:float,
     *         total_treatment_amount_formatted:string,
     *         total_discount_amount:float,
     *         total_discount_amount_formatted:string,
     *         must_pay_amount:float,
     *         must_pay_amount_formatted:string,
     *         net_collected_amount:float,
     *         net_collected_amount_formatted:string,
     *         remaining_amount:float,
     *         remaining_amount_formatted:string,
     *         balance_amount:float,
     *         balance_amount_formatted:string,
     *         balance_is_positive:bool,
     *         latest_invoice_id:?int,
     *         create_payment_url:string
     *     },
     *     balance_class:string,
     *     balance_amount_formatted:string,
     *     can_view_invoices:bool,
     *     can_view_payments:bool,
     *     can_create_payment:bool,
     *     title:string,
     *     metrics:array<int, array{label:string,value:string,value_class:?string}>,
     *     balance_text:string,
     *     actions:array<int, array{label:string,url:string,style:string,button_class:string}>,
     *     sections:array<int, array{key:string,title:string}>,
     *     sections_by_key:array<string, array{key:string,title:string}>,
     *     primary_payment_action:?array{label:string,url:string,style:string,button_class:string},
     *     secondary_payment_action:?array{label:string,url:string,style:string,button_class:string}
     * }
     */
    public function paymentPanelPayload(Patient $patient, ?User $authUser): array
    {
        $summary = $this->paymentSummary($patient);
        $capabilities = $this->workspaceCapabilities($authUser);
        $canCreatePayment = $capabilities['create_payment'] && filled($summary['create_payment_url']);
        $sections = array_values(array_filter([
            $capabilities['invoice_forms']
                ? [
                    'key' => 'invoices',
                    'title' => 'HÓA ĐƠN ĐIỀU TRỊ',
                ]
                : null,
            $capabilities['payments']
                ? [
                    'key' => 'payments',
                    'title' => 'DANH SÁCH PHIẾU THU - HOÀN ỨNG',
                ]
                : null,
        ]));
        $primaryPaymentAction = $canCreatePayment
            ? [
                'label' => 'Phiếu thu',
                'url' => $summary['create_payment_url'],
                'style' => 'primary',
                'button_class' => $this->buttonClassForStyle('primary'),
            ]
            : null;
        $secondaryPaymentAction = $canCreatePayment
            ? [
                'label' => 'Thanh toán',
                'url' => $summary['create_payment_url'],
                'style' => 'outline',
                'button_class' => $this->buttonClassForStyle('outline'),
            ]
            : null;

        return [
            'summary' => $summary,
            'title' => 'Thông tin thanh toán',
            'balance_class' => $summary['balance_is_positive'] ? 'is-positive' : 'is-negative',
            'balance_amount_formatted' => (string) $summary['balance_amount_formatted'],
            'balance_text' => 'Số dư:',
            'can_view_invoices' => $capabilities['invoice_forms'],
            'can_view_payments' => $capabilities['payments'],
            'can_create_payment' => $canCreatePayment,
            'metrics' => [
                [
                    'label' => 'Tổng tiền điều trị',
                    'value' => (string) $summary['total_treatment_amount_formatted'],
                    'value_class' => null,
                ],
                [
                    'label' => 'Giảm giá',
                    'value' => (string) $summary['total_discount_amount_formatted'],
                    'value_class' => null,
                ],
                [
                    'label' => 'Phải thanh toán',
                    'value' => (string) $summary['must_pay_amount_formatted'],
                    'value_class' => null,
                ],
                [
                    'label' => 'Đã thu',
                    'value' => (string) $summary['net_collected_amount_formatted'],
                    'value_class' => 'is-positive',
                ],
                [
                    'label' => 'Còn lại',
                    'value' => (string) $summary['remaining_amount_formatted'],
                    'value_class' => 'is-negative',
                ],
            ],
            'actions' => array_values(array_filter([
                $primaryPaymentAction,
                $secondaryPaymentAction,
            ])),
            'sections' => $sections,
            'sections_by_key' => collect($sections)
                ->keyBy('key')
                ->all(),
            'primary_payment_action' => $primaryPaymentAction,
            'secondary_payment_action' => $secondaryPaymentAction,
        ];
    }

    /**
     * @return array{
     *     treatment_plans:int,
     *     invoices:int,
     *     appointments:int,
     *     notes:int,
     *     clinical_notes:int,
     *     exam_sessions:int,
     *     photos:int,
     *     prescriptions:int,
     *     payments:int,
     *     materials:int,
     *     activity:int
     * }
     */
    public function tabCounters(Patient $patient): array
    {
        $countedPatient = Patient::query()
            ->withCount([
                'treatmentPlans',
                'invoices',
                'appointments',
                'notes',
                'clinicalNotes',
                'examSessions',
                'photos',
                'prescriptions',
                'payments',
                'branchLogs',
                'factoryOrders',
                'materialIssueNotes',
            ])
            ->findOrFail($patient->getKey());

        $materialCount = TreatmentMaterial::query()
            ->whereHas('session.treatmentPlan', fn ($query) => $query->where('patient_id', $patient->id))
            ->count();

        $factoryOrdersCount = (int) ($countedPatient->factory_orders_count ?? 0);
        $materialIssueNotesCount = (int) ($countedPatient->material_issue_notes_count ?? 0);
        $appointmentsCount = (int) ($countedPatient->appointments_count ?? 0);
        $treatmentPlansCount = (int) ($countedPatient->treatment_plans_count ?? 0);
        $invoicesCount = (int) ($countedPatient->invoices_count ?? 0);
        $paymentsCount = (int) ($countedPatient->payments_count ?? 0);
        $notesCount = (int) ($countedPatient->notes_count ?? 0);

        return [
            'treatment_plans' => $treatmentPlansCount,
            'invoices' => $invoicesCount,
            'appointments' => $appointmentsCount,
            'notes' => $notesCount,
            'clinical_notes' => (int) ($countedPatient->clinical_notes_count ?? 0),
            'exam_sessions' => (int) ($countedPatient->exam_sessions_count ?? 0),
            'photos' => (int) ($countedPatient->photos_count ?? 0),
            'prescriptions' => (int) ($countedPatient->prescriptions_count ?? 0),
            'payments' => $paymentsCount,
            'materials' => $materialCount + $factoryOrdersCount + $materialIssueNotesCount,
            'activity' => $appointmentsCount
                + $treatmentPlansCount
                + $invoicesCount
                + $paymentsCount
                + $notesCount
                + (int) ($countedPatient->branch_logs_count ?? 0),
        ];
    }

    /**
     * @return array{
     *     prescriptions:bool,
     *     appointments:bool,
     *     payments:bool,
     *     invoice_forms:bool,
     *     forms:bool,
     *     care:bool,
     *     lab_materials:bool,
     *     create_treatment_plan:bool,
     *     create_invoice:bool,
     *     create_appointment:bool,
     *     create_payment:bool
     * }
     */
    public function workspaceCapabilities(?User $authUser): array
    {
        if (! $authUser instanceof User) {
            return [
                'prescriptions' => false,
                'appointments' => false,
                'payments' => false,
                'invoice_forms' => false,
                'forms' => false,
                'care' => false,
                'lab_materials' => false,
                'create_treatment_plan' => false,
                'create_invoice' => false,
                'create_appointment' => false,
                'create_payment' => false,
            ];
        }

        $canViewPrescriptions = $authUser->can('viewAny', Prescription::class);
        $canViewAppointments = $authUser->can('viewAny', Appointment::class);
        $canViewInvoiceForms = $authUser->can('viewAny', Invoice::class);
        $canViewPayments = $canViewInvoiceForms || $authUser->can('viewAny', Payment::class);
        $canViewCare = $authUser->can('viewAny', Note::class);
        $canViewLabMaterials = $authUser->hasAnyAccessibleBranch()
            && $authUser->hasAnyRole(['Admin', 'Manager', 'Doctor']);

        return [
            'prescriptions' => $canViewPrescriptions,
            'appointments' => $canViewAppointments,
            'payments' => $canViewPayments,
            'invoice_forms' => $canViewInvoiceForms,
            'forms' => $canViewPrescriptions || $canViewInvoiceForms,
            'care' => $canViewCare,
            'lab_materials' => $canViewLabMaterials,
            'create_treatment_plan' => $authUser->can('create', TreatmentPlan::class),
            'create_invoice' => $authUser->can('create', Invoice::class),
            'create_appointment' => $authUser->can('create', Appointment::class),
            'create_payment' => $authUser->can('create', Payment::class),
        ];
    }

    /**
     * @return array<int, array{id:string,label:string,count:?int}>
     */
    public function workspaceTabs(Patient $patient, ?User $authUser): array
    {
        $counter = $this->tabCounters($patient);
        $capabilities = $this->workspaceCapabilities($authUser);

        $tabs = [
            ['id' => 'basic-info', 'label' => 'Thông tin cơ bản', 'count' => null],
            ['id' => 'exam-treatment', 'label' => 'Khám & Điều trị', 'count' => $counter['exam_sessions'] + $counter['treatment_plans']],
        ];

        if ($capabilities['prescriptions']) {
            $tabs[] = ['id' => 'prescriptions', 'label' => 'Đơn thuốc', 'count' => $counter['prescriptions']];
        }

        $tabs[] = ['id' => 'photos', 'label' => 'Thư viện ảnh', 'count' => $counter['photos']];

        if ($capabilities['lab_materials']) {
            $tabs[] = ['id' => 'lab-materials', 'label' => 'Xưởng/Vật tư', 'count' => $counter['materials']];
        }

        if ($capabilities['appointments']) {
            $tabs[] = ['id' => 'appointments', 'label' => 'Lịch hẹn', 'count' => $counter['appointments']];
        }

        if ($capabilities['payments']) {
            $tabs[] = ['id' => 'payments', 'label' => 'Thanh toán', 'count' => $counter['invoices'] + $counter['payments']];
        }

        if ($capabilities['forms']) {
            $tabs[] = [
                'id' => 'forms',
                'label' => 'Biểu mẫu',
                'count' => ($capabilities['prescriptions'] ? $counter['prescriptions'] : 0)
                    + ($capabilities['invoice_forms'] ? $counter['invoices'] : 0),
            ];
        }

        if ($capabilities['care']) {
            $tabs[] = ['id' => 'care', 'label' => 'Chăm sóc', 'count' => $counter['notes']];
        }

        $tabs[] = ['id' => 'activity-log', 'label' => 'Lịch sử thao tác', 'count' => $counter['activity']];

        return $tabs;
    }

    /**
     * @return array{
     *     create_treatment_plan:array{label:string,url:string,visible:bool,icon:string,color:string,open_in_new_tab:bool},
     *     create_invoice:array{label:string,url:string,visible:bool,icon:string,color:string,open_in_new_tab:bool},
     *     create_appointment:array{label:string,url:string,visible:bool,icon:string,color:string,open_in_new_tab:bool},
     *     medical_record:array{label:string,url:?string,visible:bool,mode:?string,record_id:?int,icon:string,color:string,open_in_new_tab:bool}
     * }
     */
    public function workspaceHeaderActions(
        Patient $patient,
        ?User $authUser,
        string $workspaceReturnUrl = '',
    ): array {
        $capabilities = $this->workspaceCapabilities($authUser);
        $medicalRecordAction = $this->medicalRecordAction(
            $patient,
            $authUser,
            includePatientContextOnEditUrl: true,
        );

        return [
            'create_treatment_plan' => [
                'label' => 'Tạo kế hoạch điều trị',
                'url' => route('filament.admin.resources.treatment-plans.create', [
                    'patient_id' => $patient->id,
                    'return_url' => $workspaceReturnUrl,
                ]),
                'visible' => $capabilities['create_treatment_plan'],
                'icon' => 'heroicon-o-clipboard-document-list',
                'color' => 'success',
                'open_in_new_tab' => true,
            ],
            'create_invoice' => [
                'label' => 'Tạo hóa đơn',
                'url' => route('filament.admin.resources.invoices.create', [
                    'patient_id' => $patient->id,
                ]),
                'visible' => $capabilities['create_invoice'],
                'icon' => 'heroicon-o-document-text',
                'color' => 'warning',
                'open_in_new_tab' => true,
            ],
            'create_appointment' => [
                'label' => 'Đặt lịch hẹn',
                'url' => route('filament.admin.resources.appointments.create', [
                    'patient_id' => $patient->id,
                ]),
                'visible' => $capabilities['create_appointment'],
                'icon' => 'heroicon-o-calendar',
                'color' => 'info',
                'open_in_new_tab' => true,
            ],
            'medical_record' => [
                'label' => $medicalRecordAction['label'] ?? 'Mở bệnh án điện tử',
                'url' => $medicalRecordAction['url'] ?? null,
                'visible' => $medicalRecordAction !== null,
                'mode' => $medicalRecordAction['mode'] ?? null,
                'record_id' => $medicalRecordAction['record_id'] ?? null,
                'icon' => 'heroicon-o-clipboard-document-check',
                'color' => 'primary',
                'open_in_new_tab' => false,
            ],
        ];
    }

    /**
     * @return array{
     *     can_view_prescriptions:bool,
     *     can_view_invoices:bool,
     *     prescriptions:Collection<int, array{
     *         record:Prescription,
     *         id:int,
     *         title:string,
     *         print_url:string
     *     }>,
     *     invoices:Collection<int, array{
     *         record:Invoice,
     *         id:int,
     *         title:string,
     *         print_url:string
     *     }>,
     *     title:string,
     *     description:string,
     *     sections:array<int, array{
     *         key:string,
     *         title:string,
     *         links:Collection<int, array{
     *             id:int,
     *             title:string,
     *             url:string,
     *             action_label:string,
     *             target:string
     *         }>,
     *         items:Collection<int, array{
     *             record:Prescription|Invoice,
     *             id:int,
     *             title:string,
     *             print_url:string
     *         }>,
     *         empty_text:string,
     *         action_label:string
     *     }>,
     *     sections_by_key:array<string, array{
     *         key:string,
     *         title:string,
     *         items:Collection<int, array{
     *             record:Prescription|Invoice,
     *             id:int,
     *             title:string,
     *             print_url:string
     *         }>,
     *         empty_text:string,
     *         action_label:string
     *     }>
     * }
     */
    public function formsPanelPayload(Patient $patient, ?User $authUser): array
    {
        $capabilities = $this->workspaceCapabilities($authUser);
        $prescriptions = $capabilities['prescriptions']
            ? $this->latestPrescriptions($patient)
            : collect();
        $invoices = $capabilities['invoice_forms']
            ? $this->latestInvoices($patient)
            : collect();
        $sections = array_values(array_filter([
            $capabilities['prescriptions']
                ? [
                    'key' => 'prescriptions',
                    'title' => 'Đơn thuốc gần nhất',
                    'links' => $prescriptions
                        ->map(fn (array $item): array => [
                            'id' => $item['id'],
                            'title' => $item['title'],
                            'url' => $item['print_url'],
                            'action_label' => 'In',
                            'target' => '_blank',
                        ])
                        ->values(),
                    'items' => $prescriptions,
                    'empty_text' => 'Chưa có đơn thuốc để in.',
                    'action_label' => 'In',
                ]
                : null,
            $capabilities['invoice_forms']
                ? [
                    'key' => 'invoices',
                    'title' => 'Hóa đơn gần nhất',
                    'links' => $invoices
                        ->map(fn (array $item): array => [
                            'id' => $item['id'],
                            'title' => $item['title'],
                            'url' => $item['print_url'],
                            'action_label' => 'In',
                            'target' => '_blank',
                        ])
                        ->values(),
                    'items' => $invoices,
                    'empty_text' => 'Chưa có hóa đơn để in.',
                    'action_label' => 'In',
                ]
                : null,
        ]));

        return [
            'can_view_prescriptions' => $capabilities['prescriptions'],
            'can_view_invoices' => $capabilities['invoice_forms'],
            'prescriptions' => $prescriptions,
            'invoices' => $invoices,
            'title' => 'Biểu mẫu & tài liệu',
            'description' => 'Truy cập nhanh biểu mẫu in theo hồ sơ bệnh nhân (đơn thuốc, hóa đơn, phiếu thu).',
            'sections' => $sections,
            'sections_by_key' => collect($sections)
                ->keyBy('key')
                ->all(),
        ];
    }

    /**
     * @return array{
     *     section_title:string,
     *     card_title:string,
     *     create_treatment_session_url:?string,
     *     sessions_count:int,
     *     days_count:int,
     *     has_day_summaries:bool,
     *     total_amount:float,
     *     total_amount_formatted:string,
     *     summary_badge:string,
     *     total_amount_text:string,
     *     empty_text:string,
     *     primary_action:?array{
     *         label:string,
     *         url:string,
     *         button_class:string
     *     }
     * }
     */
    public function treatmentProgressPanelPayload(
        Patient $patient,
        ?User $authUser,
        string $workspaceReturnUrl = '',
        ?Collection $treatmentProgress = null,
        ?Collection $treatmentProgressDaySummaries = null,
    ): array {
        $summary = $this->treatmentProgressSummary(
            $treatmentProgress ?? collect(),
            $treatmentProgressDaySummaries,
        );
        $createTreatmentSessionUrl = $authUser instanceof User
            ? route('filament.admin.resources.treatment-sessions.create', [
                'patient_id' => $patient->id,
                'return_url' => $workspaceReturnUrl,
            ])
            : null;
        $sessionsCount = (int) ($summary['sessions_count'] ?? 0);
        $daysCount = (int) ($summary['days_count'] ?? 0);
        $totalAmount = (float) ($summary['total_amount'] ?? 0);
        $totalAmountFormatted = (string) ($summary['total_amount_formatted'] ?? '0');

        return [
            'section_title' => 'Tiến trình điều trị',
            'card_title' => 'Tiến trình điều trị',
            'create_treatment_session_url' => $createTreatmentSessionUrl,
            'sessions_count' => $sessionsCount,
            'days_count' => $daysCount,
            'has_day_summaries' => $daysCount > 0,
            'total_amount' => $totalAmount,
            'total_amount_formatted' => $totalAmountFormatted,
            'summary_badge' => sprintf('%d ngày · %d phiên', $daysCount, $sessionsCount),
            'total_amount_text' => 'Tổng chi phí phiên: '.$totalAmountFormatted.'đ',
            'empty_text' => 'Chưa có tiến trình điều trị cho bệnh nhân này.',
            'primary_action' => $createTreatmentSessionUrl !== null
                ? [
                    'label' => 'Thêm ngày điều trị',
                    'url' => $createTreatmentSessionUrl,
                    'button_class' => $this->buttonClassForStyle('primary'),
                ]
                : null,
        ];
    }

    /**
     * @return Collection<int, array{
     *     record:FactoryOrder,
     *     id:int,
     *     order_no:string,
     *     status:string,
     *     status_label:string,
     *     status_class:string,
     *     priority:string,
     *     ordered_at:?Carbon,
     *     ordered_at_formatted:string,
     *     due_at:?Carbon,
     *     due_at_formatted:string,
     *     delivered_at:?Carbon,
     *     notes:?string,
     *     items_count:int,
     *     items_count_formatted:string,
     *     detail_url:?string,
     *     detail_action_label:string,
     *     detail_action_class:string,
     *     detail_action:array{
     *         url:?string,
     *         label:string,
     *         class:string
     *     }
     * }>
     */
    public function factoryOrders(Patient $patient, ?User $authUser = null): Collection
    {
        $canAccessFactoryOrders = FactoryOrderResource::canAccess();

        return $patient->factoryOrders()
            ->withCount('items')
            ->latest('ordered_at')
            ->latest('id')
            ->limit(20)
            ->get([
                'id',
                'order_no',
                'patient_id',
                'status',
                'priority',
                'ordered_at',
                'due_at',
                'delivered_at',
                'notes',
            ])
            ->map(function (FactoryOrder $order) use ($authUser, $canAccessFactoryOrders): array {
                $detailUrl = $canAccessFactoryOrders && $authUser?->can('update', $order)
                    ? route('filament.admin.resources.factory-orders.edit', ['record' => $order->id])
                    : null;
                $detailActionLabel = $detailUrl !== null ? 'Chi tiết' : 'Không có quyền';
                $detailActionClass = $detailUrl !== null
                    ? 'text-sm font-medium text-primary-600 hover:underline'
                    : 'text-sm text-gray-500';

                return [
                    'record' => $order,
                    'id' => $order->id,
                    'order_no' => (string) $order->order_no,
                    'status' => (string) $order->status,
                    'status_label' => $this->factoryOrderStatusLabel((string) $order->status),
                    'status_class' => $this->factoryOrderStatusClass((string) $order->status),
                    'priority' => (string) $order->priority,
                    'ordered_at' => $order->ordered_at,
                    'ordered_at_formatted' => $order->ordered_at?->format('d/m/Y H:i') ?? '-',
                    'due_at' => $order->due_at,
                    'due_at_formatted' => $order->due_at?->format('d/m/Y H:i') ?? '-',
                    'delivered_at' => $order->delivered_at,
                    'notes' => $order->notes,
                    'items_count' => (int) ($order->items_count ?? 0),
                    'items_count_formatted' => number_format((int) ($order->items_count ?? 0), 0, ',', '.'),
                    'detail_url' => $detailUrl,
                    'detail_action_label' => $detailActionLabel,
                    'detail_action_class' => $detailActionClass,
                    'detail_action' => [
                        'url' => $detailUrl,
                        'label' => $detailActionLabel,
                        'class' => $detailActionClass,
                    ],
                ];
            });
    }

    /**
     * @return Collection<int, array{
     *     record:MaterialIssueNote,
     *     id:int,
     *     note_no:string,
     *     status:string,
     *     status_label:string,
     *     status_class:string,
     *     issued_at:?Carbon,
     *     issued_at_formatted:string,
     *     posted_at:?Carbon,
     *     reason:?string,
     *     notes:?string,
     *     items_count:int,
     *     items_count_formatted:string,
     *     total_cost:float,
     *     total_cost_formatted:string,
     *     detail_url:?string,
     *     detail_action_label:string,
     *     detail_action_class:string,
     *     detail_action:array{
     *         url:?string,
     *         label:string,
     *         class:string
     *     }
     * }>
     */
    public function materialIssueNotes(Patient $patient, ?User $authUser = null): Collection
    {
        $canAccessMaterialIssueNotes = MaterialIssueNoteResource::canAccess();

        return $patient->materialIssueNotes()
            ->withCount('items')
            ->withSum('items as total_cost', 'total_cost')
            ->latest('issued_at')
            ->latest('id')
            ->limit(20)
            ->get([
                'id',
                'note_no',
                'patient_id',
                'status',
                'issued_at',
                'posted_at',
                'reason',
                'notes',
            ])
            ->map(function (MaterialIssueNote $note) use ($canAccessMaterialIssueNotes): array {
                $totalCost = (float) ($note->total_cost ?? 0);
                $detailUrl = $canAccessMaterialIssueNotes
                    ? route('filament.admin.resources.material-issue-notes.edit', ['record' => $note->id])
                    : null;
                $detailActionLabel = $detailUrl !== null ? 'Chi tiết' : 'Không có quyền';
                $detailActionClass = $detailUrl !== null
                    ? 'text-sm font-medium text-primary-600 hover:underline'
                    : 'text-sm text-gray-500';

                return [
                    'record' => $note,
                    'id' => $note->id,
                    'note_no' => (string) $note->note_no,
                    'status' => (string) $note->status,
                    'status_label' => $this->materialIssueStatusLabel((string) $note->status),
                    'status_class' => $this->materialIssueStatusClass((string) $note->status),
                    'issued_at' => $note->issued_at,
                    'issued_at_formatted' => $note->issued_at?->format('d/m/Y H:i') ?? '-',
                    'posted_at' => $note->posted_at,
                    'reason' => $note->reason,
                    'notes' => $note->notes,
                    'items_count' => (int) ($note->items_count ?? 0),
                    'items_count_formatted' => number_format((int) ($note->items_count ?? 0), 0, ',', '.'),
                    'total_cost' => $totalCost,
                    'total_cost_formatted' => $this->formatMoney($totalCost),
                    'detail_url' => $detailUrl,
                    'detail_action_label' => $detailActionLabel,
                    'detail_action_class' => $detailActionClass,
                    'detail_action' => [
                        'url' => $detailUrl,
                        'label' => $detailActionLabel,
                        'class' => $detailActionClass,
                    ],
                ];
            });
    }

    /**
     * @return Collection<int, array{
     *     record:TreatmentMaterial,
     *     id:int,
     *     created_at:?Carbon,
     *     created_at_formatted:string,
     *     treatment_session_id:?int,
     *     treatment_session_label:string,
     *     material_name:string,
     *     quantity:float,
     *     quantity_formatted:string,
     *     unit_cost:float,
     *     unit_cost_formatted:string,
     *     total_cost:float,
     *     total_cost_formatted:string,
     *     user_name:string
     * }>
     */
    public function materialUsages(Patient $patient, ?User $authUser = null): Collection
    {
        return TreatmentMaterial::query()
            ->with(['session', 'material', 'user'])
            ->whereHas('session.treatmentPlan', fn ($query) => $query->where('patient_id', $patient->id))
            ->latest('created_at')
            ->limit(100)
            ->get()
            ->map(function (TreatmentMaterial $usage): array {
                $quantity = (float) $usage->quantity;
                $totalCost = (float) $usage->cost;
                $unitCost = round($totalCost / max($quantity, 1), 2);

                return [
                    'record' => $usage,
                    'id' => $usage->id,
                    'created_at' => $usage->created_at,
                    'created_at_formatted' => $usage->created_at?->format('d/m/Y H:i') ?? '-',
                    'treatment_session_id' => $usage->treatment_session_id ? (int) $usage->treatment_session_id : null,
                    'treatment_session_label' => $usage->treatment_session_id !== null
                        ? '#'.$usage->treatment_session_id
                        : '-',
                    'material_name' => $usage->material?->name ?? 'N/A',
                    'quantity' => $quantity,
                    'quantity_formatted' => $this->formatMoney($quantity),
                    'unit_cost' => $unitCost,
                    'unit_cost_formatted' => $this->formatMoney($unitCost),
                    'total_cost' => $totalCost,
                    'total_cost_formatted' => $this->formatMoney($totalCost),
                    'user_name' => $usage->user?->name ?? 'N/A',
                ];
            });
    }

    /**
     * @return array{
     *     create_factory_order_url:?string,
     *     create_material_issue_note_url:?string,
     *     create_treatment_material_url:?string,
     *     sections:array<int, array{
     *         key:string,
     *         title:string,
     *         description:string,
     *         empty_text:string,
     *         action:?array{
     *             label:string,
     *             url:string,
     *             style:string,
     *             button_class:string
     *         }
     *     }>,
     *     sections_by_key:array<string, array{
     *         key:string,
     *         title:string,
     *         description:string,
     *         empty_text:string,
     *         action:?array{
     *             label:string,
     *             url:string,
     *             style:string,
     *             button_class:string
     *         }
     *     }>
     * }
     */
    public function labMaterialsPanelPayload(Patient $patient, ?User $authUser): array
    {
        $createFactoryOrderUrl = FactoryOrderResource::canAccess() && $authUser?->can('create', FactoryOrder::class)
            ? route('filament.admin.resources.factory-orders.create', [
                'patient_id' => $patient->id,
                'branch_id' => $patient->first_branch_id,
            ])
            : null;
        $createMaterialIssueNoteUrl = MaterialIssueNoteResource::canAccess()
            ? route('filament.admin.resources.material-issue-notes.create', [
                'patient_id' => $patient->id,
                'branch_id' => $patient->first_branch_id,
            ])
            : null;
        $createTreatmentMaterialUrl = TreatmentMaterialResource::canAccess()
            ? route('filament.admin.resources.treatment-materials.create')
            : null;
        $sections = [
            [
                'key' => 'factory_orders',
                'title' => 'Xưởng/Labo',
                'description' => 'Theo dõi lệnh labo theo hồ sơ bệnh nhân và tiến độ giao hàng.',
                'empty_text' => 'Chưa có lệnh labo cho bệnh nhân này.',
                'action' => $createFactoryOrderUrl !== null
                    ? [
                        'label' => 'Tạo lệnh labo',
                        'url' => $createFactoryOrderUrl,
                        'style' => 'primary',
                        'button_class' => $this->buttonClassForStyle('primary'),
                    ]
                    : null,
            ],
            [
                'key' => 'material_issue_notes',
                'title' => 'Phiếu xuất vật tư',
                'description' => 'Xuất kho theo bệnh nhân, đồng bộ tồn kho và chi phí vật tư.',
                'empty_text' => 'Chưa có phiếu xuất vật tư cho bệnh nhân này.',
                'action' => $createMaterialIssueNoteUrl !== null
                    ? [
                        'label' => 'Tạo phiếu xuất',
                        'url' => $createMaterialIssueNoteUrl,
                        'style' => 'primary',
                        'button_class' => $this->buttonClassForStyle('primary'),
                    ]
                    : null,
            ],
            [
                'key' => 'treatment_materials',
                'title' => 'Vật tư đã dùng trong phiên điều trị',
                'description' => 'Đối soát vật tư đã sử dụng trực tiếp theo từng phiên điều trị.',
                'empty_text' => 'Chưa có dữ liệu vật tư cho bệnh nhân này.',
                'action' => $createTreatmentMaterialUrl !== null
                    ? [
                        'label' => 'Thêm vật tư phiên',
                        'url' => $createTreatmentMaterialUrl,
                        'style' => 'outline',
                        'button_class' => $this->buttonClassForStyle('outline'),
                    ]
                    : null,
            ],
        ];

        return [
            'create_factory_order_url' => $createFactoryOrderUrl,
            'create_material_issue_note_url' => $createMaterialIssueNoteUrl,
            'create_treatment_material_url' => $createTreatmentMaterialUrl,
            'sections' => $sections,
            'sections_by_key' => collect($sections)
                ->keyBy('key')
                ->all(),
        ];
    }

    /**
     * @return Collection<int, array{
     *     record:Prescription,
     *     id:int,
     *     title:string,
     *     print_url:string
     * }>
     */
    public function latestPrescriptions(Patient $patient): Collection
    {
        return $patient->prescriptions()
            ->latest('created_at')
            ->limit(5)
            ->get([
                'id',
                'patient_id',
                'prescription_code',
                'treatment_date',
                'created_at',
            ])
            ->map(function (Prescription $prescription): array {
                return [
                    'record' => $prescription,
                    'id' => $prescription->id,
                    'title' => sprintf(
                        '%s - %s',
                        $prescription->prescription_code,
                        $prescription->treatment_date?->format('d/m/Y') ?? '-',
                    ),
                    'print_url' => route('prescriptions.print', $prescription),
                ];
            });
    }

    /**
     * @return Collection<int, array{
     *     record:Invoice,
     *     id:int,
     *     title:string,
     *     print_url:string
     * }>
     */
    public function latestInvoices(Patient $patient): Collection
    {
        return $patient->invoices()
            ->latest('created_at')
            ->limit(5)
            ->get([
                'id',
                'patient_id',
                'invoice_no',
                'issued_at',
                'created_at',
            ])
            ->map(function (Invoice $invoice): array {
                return [
                    'record' => $invoice,
                    'id' => $invoice->id,
                    'title' => sprintf(
                        '#%s - %s',
                        $invoice->invoice_no,
                        $invoice->issued_at?->format('d/m/Y') ?? $invoice->created_at?->format('d/m/Y'),
                    ),
                    'print_url' => route('invoices.print', $invoice),
                ];
            });
    }

    /**
     * @return array{
     *     label:string,
     *     url:string,
     *     mode:'create'|'edit',
     *     record_id:?int
     * }|null
     */
    public function medicalRecordAction(
        Patient $patient,
        ?User $authUser,
        bool $includePatientContextOnEditUrl = false,
    ): ?array {
        if (! $authUser instanceof User) {
            return null;
        }

        $medicalRecord = $patient->medicalRecord()
            ->first(['id', 'patient_id']);

        if ($medicalRecord instanceof PatientMedicalRecord) {
            if (! ($authUser->can('update', $medicalRecord) || $authUser->can('view', $medicalRecord))) {
                return null;
            }

            $editParameters = [
                'record' => $medicalRecord->id,
            ];

            if ($includePatientContextOnEditUrl) {
                $editParameters['patient_id'] = $patient->id;
            }

            return [
                'label' => 'Mở bệnh án điện tử',
                'url' => PatientMedicalRecordResource::getUrl('edit', $editParameters),
                'mode' => 'edit',
                'record_id' => $medicalRecord->id,
            ];
        }

        if (! $authUser->can('create', PatientMedicalRecord::class)) {
            return null;
        }

        return [
            'label' => 'Tạo bệnh án điện tử',
            'url' => PatientMedicalRecordResource::getUrl('create', [
                'patient_id' => $patient->id,
            ]),
            'mode' => 'create',
            'record_id' => null,
        ];
    }

    /**
     * @return Collection<int, array{
     *     session_id:int,
     *     performed_at:string,
     *     progress_date:string,
     *     tooth_label:string,
     *     plan_item_name:string,
     *     procedure:string,
     *     doctor_name:string,
     *     assistant_name:string,
     *     quantity:int|float,
     *     price_formatted:string,
     *     total_amount:float,
     *     total_amount_formatted:string,
     *     status_label:string,
     *     status_class:string,
     *     edit_url:?string,
     *     edit_action_label:string,
     *     edit_action:?array{
     *         url:string,
     *         label:string,
     *         icon:string,
     *         button_class:string
     *     },
     *     edit_action_placeholder:string
     * }>
     */
    public function treatmentProgress(Patient $patient, string $workspaceReturnUrl = ''): Collection
    {
        return $patient->treatmentProgressItems()
            ->with([
                'doctor:id,name',
                'assistant:id,name',
                'planItem:id,name,tooth_number,tooth_ids,quantity,price,status',
                'progressDay:id,progress_date,status',
            ])
            ->latest('performed_at')
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(function ($progressItem) use ($workspaceReturnUrl): array {
                $performedAt = $progressItem->performed_at ?? $progressItem->created_at;
                $progressDate = $progressItem->progressDay?->progress_date?->format('d/m/Y')
                    ?? $performedAt?->format('d/m/Y')
                    ?? '-';
                $statusLabel = match ($progressItem->status) {
                    'completed' => 'Hoàn thành',
                    'in_progress' => 'Đang thực hiện',
                    'cancelled' => 'Đã hủy',
                    default => 'Đã lên lịch',
                };
                $statusClass = match ($progressItem->status) {
                    'completed' => 'is-completed',
                    'in_progress' => 'is-progress',
                    'cancelled' => 'is-default',
                    default => 'is-default',
                };
                $toothLabel = $progressItem->tooth_number
                    ?: $progressItem->planItem?->tooth_number
                    ?: (is_array($progressItem->planItem?->tooth_ids) ? implode(' ', $progressItem->planItem?->tooth_ids) : '-');
                $sessionQty = (float) ($progressItem->quantity ?? 1);
                $sessionPrice = (float) ($progressItem->unit_price ?? 0);
                $sessionLineTotal = (float) ($progressItem->total_amount ?? ($sessionQty * $sessionPrice));
                $sessionId = $progressItem->treatment_session_id ? (int) $progressItem->treatment_session_id : null;
                $editUrl = $sessionId
                    ? route('filament.admin.resources.treatment-sessions.edit', [
                        'record' => $sessionId,
                        'return_url' => $workspaceReturnUrl,
                    ])
                    : null;

                return [
                    'session_id' => $progressItem->id,
                    'performed_at' => $performedAt?->format('d/m/Y H:i') ?? '-',
                    'progress_date' => $progressDate,
                    'tooth_label' => $toothLabel,
                    'plan_item_name' => $progressItem->procedure_name ?: ($progressItem->planItem?->name ?? '-'),
                    'procedure' => $progressItem->notes ?: '-',
                    'doctor_name' => $progressItem->doctor?->name ?? '-',
                    'assistant_name' => $progressItem->assistant?->name ?? '-',
                    'quantity' => fmod($sessionQty, 1.0) === 0.0 ? (int) $sessionQty : $sessionQty,
                    'price_formatted' => $this->formatMoney($sessionPrice),
                    'total_amount' => $sessionLineTotal,
                    'total_amount_formatted' => $this->formatMoney($sessionLineTotal),
                    'status_label' => $statusLabel,
                    'status_class' => $statusClass,
                    'edit_url' => $editUrl,
                    'edit_action_label' => 'Chỉnh sửa phiên điều trị',
                    'edit_action' => $editUrl !== null
                        ? [
                            'url' => $editUrl,
                            'label' => 'Chỉnh sửa phiên điều trị',
                            'icon' => 'heroicon-o-pencil-square',
                            'button_class' => 'crm-table-icon-btn',
                        ]
                        : null,
                    'edit_action_placeholder' => '-',
                ];
            });
    }

    /**
     * @return Collection<int, array{
     *     progress_date:string,
     *     status_label:string,
     *     sessions_count:int,
     *     day_total_amount:float,
     *     day_total_amount_formatted:string
     * }>
     */
    public function treatmentProgressDaySummaries(Patient $patient): Collection
    {
        return $patient->treatmentProgressDays()
            ->withSum('items as day_total_amount', 'total_amount')
            ->withCount('items as sessions_count')
            ->latest('progress_date')
            ->latest('id')
            ->limit(20)
            ->get(['id', 'progress_date', 'status'])
            ->map(function ($day): array {
                $statusLabel = match ($day->status) {
                    'completed' => 'Hoàn thành',
                    'in_progress' => 'Đang thực hiện',
                    'locked' => 'Đã khoá',
                    default => 'Đã lên lịch',
                };

                return [
                    'progress_date' => $day->progress_date?->format('d/m/Y') ?? '-',
                    'status_label' => $statusLabel,
                    'sessions_count' => (int) ($day->sessions_count ?? 0),
                    'day_total_amount' => (float) ($day->day_total_amount ?? 0),
                    'day_total_amount_formatted' => $this->formatMoney((float) ($day->day_total_amount ?? 0)),
                ];
            });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $treatmentProgress
     * @param  Collection<int, array<string, mixed>>|null  $treatmentProgressDaySummaries
     * @return array{
     *     sessions_count:int,
     *     days_count:int,
     *     total_amount:float,
     *     total_amount_formatted:string
     * }
     */
    public function treatmentProgressSummary(
        Collection $treatmentProgress,
        ?Collection $treatmentProgressDaySummaries = null,
    ): array {
        $totalAmount = round(
            (float) $treatmentProgress->sum(fn (array $session): float => (float) ($session['total_amount'] ?? 0)),
            2,
        );

        return [
            'sessions_count' => $treatmentProgress->count(),
            'days_count' => $treatmentProgressDaySummaries?->count() ?? 0,
            'total_amount' => $totalAmount,
            'total_amount_formatted' => $this->formatMoney($totalAmount),
        ];
    }

    protected function formatMoney(float|int|string|null $value): string
    {
        return number_format((float) $value, 0, ',', '.');
    }

    protected function buttonClassForStyle(string $style): string
    {
        return $style === 'outline'
            ? 'crm-btn-outline'
            : 'crm-btn-primary';
    }

    protected function factoryOrderStatusLabel(string $status): string
    {
        return match ($status) {
            FactoryOrder::STATUS_ORDERED => 'Đã đặt',
            FactoryOrder::STATUS_IN_PROGRESS => 'Đang làm',
            FactoryOrder::STATUS_DELIVERED => 'Đã giao',
            FactoryOrder::STATUS_CANCELLED => 'Đã hủy',
            default => 'Nháp',
        };
    }

    protected function factoryOrderStatusClass(string $status): string
    {
        return match ($status) {
            FactoryOrder::STATUS_DELIVERED => 'is-completed',
            FactoryOrder::STATUS_ORDERED, FactoryOrder::STATUS_IN_PROGRESS => 'is-progress',
            default => 'is-default',
        };
    }

    protected function materialIssueStatusLabel(string $status): string
    {
        return match ($status) {
            MaterialIssueNote::STATUS_POSTED => 'Đã xuất kho',
            MaterialIssueNote::STATUS_CANCELLED => 'Đã hủy',
            default => 'Nháp',
        };
    }

    protected function materialIssueStatusClass(string $status): string
    {
        return match ($status) {
            MaterialIssueNote::STATUS_POSTED => 'is-completed',
            default => 'is-default',
        };
    }

    protected function patientInitials(string $fullName): string
    {
        $name = trim($fullName);

        if ($name === '') {
            return '?';
        }

        $parts = preg_split('/\s+/', $name) ?: [];
        $first = mb_substr($parts[0] ?? '', 0, 1);
        $last = mb_substr($parts[count($parts) - 1] ?? '', 0, 1);

        return mb_strtoupper($first.$last);
    }

    protected function patientGenderLabel(?string $gender): ?string
    {
        return match ($gender) {
            'male' => 'Nam',
            'female' => 'Nữ',
            default => null,
        };
    }

    protected function patientGenderBadgeClass(?string $gender): ?string
    {
        return match ($gender) {
            'male' => 'is-male',
            'female' => 'is-female',
            default => null,
        };
    }
}
