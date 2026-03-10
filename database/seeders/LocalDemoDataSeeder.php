<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\BranchLog;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\DoctorBranchAssignment;
use App\Models\FactoryOrder;
use App\Models\FactoryOrderItem;
use App\Models\Invoice;
use App\Models\Note;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\PlanItem;
use App\Models\PromotionGroup;
use App\Models\ReceiptExpense;
use App\Models\Service;
use App\Models\Supplier;
use App\Models\TreatmentPlan;
use App\Models\TreatmentSession;
use App\Models\User;
use App\Models\ZnsCampaign;
use App\Models\ZnsCampaignDelivery;
use App\Support\PatientCodeGenerator;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema as DatabaseSchema;
use Illuminate\Support\Str;

class LocalDemoDataSeeder extends Seeder
{
    use WithoutModelEvents;

    public const DEFAULT_DEMO_PASSWORD = 'Demo@123456';

    /**
     * @var array<string, array<int, string>>
     */
    public const DEMO_MFA_RECOVERY_CODES = [
        'admin@demo.ident.test' => [
            'qa-admin-0001',
            'qa-admin-0002',
            'qa-admin-0003',
            'qa-admin-0004',
            'qa-admin-0005',
            'qa-admin-0006',
            'qa-admin-0007',
            'qa-admin-0008',
        ],
        'manager.q1@demo.ident.test' => [
            'qa-q1-mgr-0001',
            'qa-q1-mgr-0002',
            'qa-q1-mgr-0003',
            'qa-q1-mgr-0004',
            'qa-q1-mgr-0005',
            'qa-q1-mgr-0006',
            'qa-q1-mgr-0007',
            'qa-q1-mgr-0008',
        ],
        'manager.cg@demo.ident.test' => [
            'qa-cg-mgr-0001',
            'qa-cg-mgr-0002',
            'qa-cg-mgr-0003',
            'qa-cg-mgr-0004',
            'qa-cg-mgr-0005',
            'qa-cg-mgr-0006',
            'qa-cg-mgr-0007',
            'qa-cg-mgr-0008',
        ],
        'manager.hc@demo.ident.test' => [
            'qa-hc-mgr-0001',
            'qa-hc-mgr-0002',
            'qa-hc-mgr-0003',
            'qa-hc-mgr-0004',
            'qa-hc-mgr-0005',
            'qa-hc-mgr-0006',
            'qa-hc-mgr-0007',
            'qa-hc-mgr-0008',
        ],
    ];

    public function run(): void
    {
        $branches = $this->seedBranches();
        $this->seedUsers($branches);

        $this->call([
            ClinicSettingsSeeder::class,
            InventorySeeder::class,
        ]);

        $customerGroupIds = CustomerGroup::query()->pluck('id', 'code');
        $promotionGroupIds = PromotionGroup::query()->pluck('id', 'code');
        $usersByEmail = User::query()->pluck('id', 'email');
        $branchesByCode = $branches->pluck('id', 'code');

        $this->seedCustomers($branchesByCode, $customerGroupIds, $promotionGroupIds, $usersByEmail);
        $this->seedPatients($branchesByCode, $customerGroupIds, $promotionGroupIds, $usersByEmail);
        $customersByPhone = Customer::query()
            ->get()
            ->keyBy(fn (Customer $customer): string => (string) $customer->phone);
        $patientsByPhone = Patient::query()
            ->get()
            ->keyBy(fn (Patient $patient): string => (string) $patient->phone);

        $this->seedTreatmentJourney($branches, $usersByEmail, $patientsByPhone, $customersByPhone);

        $this->call([
            OpsScenarioSeeder::class,
            IntegrationScenarioSeeder::class,
            KpiScenarioSeeder::class,
            ZnsAutomationScenarioSeeder::class,
            PatientScenarioSeeder::class,
            AppointmentScenarioSeeder::class,
            CareScenarioSeeder::class,
            FinanceScenarioSeeder::class,
            InventoryScenarioSeeder::class,
            SupplierScenarioSeeder::class,
            TreatmentScenarioSeeder::class,
            ClinicalScenarioSeeder::class,
            GovernanceScenarioSeeder::class,
        ]);
    }

    /**
     * @return array<int, string>
     */
    public static function demoMfaRecoveryCodesFor(string $email): array
    {
        return self::DEMO_MFA_RECOVERY_CODES[$email] ?? [];
    }

    protected function seedBranches(): Collection
    {
        $definitions = [
            [
                'code' => 'HCM-Q1',
                'name' => 'Nha khoa Ident Quan 1',
                'address' => '35 Nguyen Binh Khiem, Ben Nghe, Quan 1, TP.HCM',
                'phone' => '02836225501',
                'active' => true,
            ],
            [
                'code' => 'HN-CG',
                'name' => 'Nha khoa Ident Cau Giay',
                'address' => '88 Tran Thai Tong, Dich Vong Hau, Cau Giay, Ha Noi',
                'phone' => '02437668801',
                'active' => true,
            ],
            [
                'code' => 'DN-HC',
                'name' => 'Nha khoa Ident Hai Chau',
                'address' => '215 Nguyen Van Linh, Nam Duong, Hai Chau, Da Nang',
                'phone' => '02363888801',
                'active' => true,
            ],
        ];

        return collect($definitions)
            ->map(fn (array $definition): Branch => Branch::query()->updateOrCreate(
                ['code' => $definition['code']],
                $definition,
            ))
            ->values();
    }

    protected function seedUsers(Collection $branches): User
    {
        $branchIdsByCode = $branches->pluck('id', 'code');

        $accounts = [
            [
                'email' => 'admin@demo.ident.test',
                'name' => 'Quan tri he thong',
                'branch_id' => $branchIdsByCode['HCM-Q1'],
                'phone' => '0909000001',
                'role' => 'Admin',
                'specialty' => null,
            ],
            [
                'email' => 'automation.bot@demo.ident.test',
                'name' => 'Automation Service Bot',
                'branch_id' => null,
                'phone' => null,
                'role' => 'AutomationService',
                'specialty' => null,
            ],
            [
                'email' => 'manager.q1@demo.ident.test',
                'name' => 'Quan ly Quan 1',
                'branch_id' => $branchIdsByCode['HCM-Q1'],
                'phone' => '0909000011',
                'role' => 'Manager',
                'specialty' => null,
            ],
            [
                'email' => 'manager.cg@demo.ident.test',
                'name' => 'Quan ly Cau Giay',
                'branch_id' => $branchIdsByCode['HN-CG'],
                'phone' => '0909000012',
                'role' => 'Manager',
                'specialty' => null,
            ],
            [
                'email' => 'manager.hc@demo.ident.test',
                'name' => 'Quan ly Hai Chau',
                'branch_id' => $branchIdsByCode['DN-HC'],
                'phone' => '0909000013',
                'role' => 'Manager',
                'specialty' => null,
            ],
            [
                'email' => 'doctor.q1@demo.ident.test',
                'name' => 'Bac si Tran Minh Khoi',
                'branch_id' => $branchIdsByCode['HCM-Q1'],
                'phone' => '0909000021',
                'role' => 'Doctor',
                'specialty' => 'Implant',
            ],
            [
                'email' => 'doctor.cg@demo.ident.test',
                'name' => 'Bac si Nguyen Ngoc Lan',
                'branch_id' => $branchIdsByCode['HN-CG'],
                'phone' => '0909000022',
                'role' => 'Doctor',
                'specialty' => 'Nieng rang',
            ],
            [
                'email' => 'doctor.hc@demo.ident.test',
                'name' => 'Bac si Le Quoc Bao',
                'branch_id' => $branchIdsByCode['DN-HC'],
                'phone' => '0909000023',
                'role' => 'Doctor',
                'specialty' => 'Tong quat',
            ],
            [
                'email' => 'doctor.float@demo.ident.test',
                'name' => 'Bac si Tran Phuong Linh',
                'branch_id' => $branchIdsByCode['HCM-Q1'],
                'phone' => '0909000024',
                'role' => 'Doctor',
                'specialty' => 'Phuc hinh',
            ],
            [
                'email' => 'cskh.q1@demo.ident.test',
                'name' => 'Tu van / Le tan Pham Thu Ha',
                'branch_id' => $branchIdsByCode['HCM-Q1'],
                'phone' => '0909000031',
                'role' => 'CSKH',
                'specialty' => null,
            ],
            [
                'email' => 'cskh.cg@demo.ident.test',
                'name' => 'Tu van / Le tan Vo Thao My',
                'branch_id' => $branchIdsByCode['HN-CG'],
                'phone' => '0909000032',
                'role' => 'CSKH',
                'specialty' => null,
            ],
            [
                'email' => 'cskh.hc@demo.ident.test',
                'name' => 'Tu van / Le tan Nguyen Bao Tram',
                'branch_id' => $branchIdsByCode['DN-HC'],
                'phone' => '0909000033',
                'role' => 'CSKH',
                'specialty' => null,
            ],
        ];

        /** @var User|null $admin */
        $admin = null;

        foreach ($accounts as $account) {
            $role = $account['role'];
            unset($account['role']);

            $user = $this->upsertDemoUser($account);

            $user->syncRoles([$role]);
            $this->syncDemoMfaState($user, $role);

            if ($role === 'Admin') {
                $admin = $user;
            }
        }

        $this->renderSensitiveAccountMfaHints();

        $admin ??= User::query()->where('email', 'admin@demo.ident.test')->firstOrFail();

        $this->seedDoctorAssignments($branchIdsByCode, $admin->id);

        return $admin;
    }

    /**
     * @param  array{name:string,email:string,branch_id:int|null,phone:?string,specialty:?string}  $attributes
     */
    protected function upsertDemoUser(array $attributes): User
    {
        $user = User::query()->firstOrNew([
            'email' => $attributes['email'],
        ]);

        $user->fill($attributes);

        if (! Hash::check(self::DEFAULT_DEMO_PASSWORD, (string) $user->password)) {
            $user->password = self::DEFAULT_DEMO_PASSWORD;
        }

        $user->email_verified_at ??= now();
        $user->save();

        return $user;
    }

    protected function syncDemoMfaState(User $user, string $role): void
    {
        $requiresMfa = in_array($role, ['Admin', 'Manager'], true);
        $shouldPreEnrollMfa = $requiresMfa && (bool) config('care.security_seed_demo_mfa', false);
        $confirmedAt = $shouldPreEnrollMfa ? now()->subDay() : null;

        $user->forceFill([
            'two_factor_confirmed_at' => $confirmedAt,
        ])->saveQuietly();

        if (! $shouldPreEnrollMfa) {
            $user->breezySessions()->delete();

            return;
        }

        $user->breezySessions()->updateOrCreate(
            [
                'panel_id' => 'admin',
            ],
            [
                'two_factor_secret' => Str::upper(Str::random(32)),
                'two_factor_recovery_codes' => self::demoMfaRecoveryCodesFor($user->email) ?: $user->generateRecoveryCodes(),
                'two_factor_confirmed_at' => $confirmedAt,
            ],
        );
    }

    protected function renderSensitiveAccountMfaHints(): void
    {
        if (app()->runningUnitTests() || $this->command === null) {
            return;
        }

        if (! (bool) config('care.security_seed_demo_mfa', false)) {
            $this->command->info('Sensitive demo accounts are seeded without MFA. Non-production bootstrap bypass stays open until the first Admin or Manager configures MFA.');

            return;
        }

        $rows = collect(self::DEMO_MFA_RECOVERY_CODES)
            ->map(fn (array $codes, string $email): array => [
                'email' => $email,
                'password' => self::DEFAULT_DEMO_PASSWORD,
                'recovery_codes' => implode(', ', $codes),
            ])
            ->values()
            ->all();

        if ($rows === []) {
            return;
        }

        $this->command->info('Demo MFA QA credentials');
        $this->command->table(
            ['Email', 'Password', 'Recovery codes'],
            $rows,
        );
    }

    protected function seedDoctorAssignments(Collection $branchIdsByCode, int $adminId): void
    {
        $doctor = User::query()->where('email', 'doctor.float@demo.ident.test')->first();

        if (! $doctor instanceof User) {
            return;
        }

        $assignmentDefinitions = [
            [
                'branch_code' => 'HCM-Q1',
                'is_primary' => true,
                'note' => 'Bac si phu trach co so chinh Quan 1.',
            ],
            [
                'branch_code' => 'HN-CG',
                'is_primary' => false,
                'note' => 'Bac si ho tro lich hen lien chi nhanh cho demo branch scope.',
            ],
        ];

        foreach ($assignmentDefinitions as $assignment) {
            $branchId = $branchIdsByCode->get($assignment['branch_code']);

            if (! is_numeric($branchId)) {
                continue;
            }

            DoctorBranchAssignment::query()->updateOrCreate(
                [
                    'user_id' => $doctor->id,
                    'branch_id' => (int) $branchId,
                ],
                [
                    'is_active' => true,
                    'is_primary' => $assignment['is_primary'],
                    'assigned_from' => now()->startOfMonth()->toDateString(),
                    'assigned_until' => null,
                    'created_by' => $adminId,
                    'note' => $assignment['note'],
                ],
            );
        }
    }

    protected function seedCustomers(
        Collection $branchesByCode,
        Collection $customerGroupIds,
        Collection $promotionGroupIds,
        Collection $usersByEmail,
    ): void {
        if (Customer::query()->exists()) {
            return;
        }

        foreach ($this->customerSeedRows() as $row) {
            $branchId = $branchesByCode->get($row['branch_code']);
            $assignedTo = $usersByEmail->get($row['assigned_to_email']);

            if (! is_numeric($branchId)) {
                continue;
            }

            $phoneSearchHash = Customer::phoneSearchHash($row['phone']);
            $customer = Customer::query()
                ->where('branch_id', (int) $branchId)
                ->where('phone_search_hash', $phoneSearchHash)
                ->first() ?? new Customer;

            $customer->fill([
                'branch_id' => (int) $branchId,
                'full_name' => $row['full_name'],
                'phone' => $row['phone'],
                'phone_search_hash' => Customer::phoneSearchHash($row['phone']),
                'email' => $row['email'],
                'email_search_hash' => Customer::emailSearchHash($row['email']),
                'birthday' => $row['birthday'],
                'gender' => $row['gender'],
                'address' => $row['address'],
                'source' => $row['source'],
                'source_detail' => $row['source_detail'],
                'customer_group_id' => $customerGroupIds->get($row['customer_group_code']),
                'promotion_group_id' => $promotionGroupIds->get($row['promotion_group_code']),
                'status' => $row['status'],
                'assigned_to' => is_numeric($assignedTo) ? (int) $assignedTo : null,
                'next_follow_up_at' => $row['next_follow_up_at'],
                'notes' => $row['notes'],
                'created_by' => $usersByEmail->get('admin@demo.ident.test'),
                'updated_by' => $usersByEmail->get('admin@demo.ident.test'),
            ]);
            $customer->save();
        }
    }

    protected function seedPatients(
        Collection $branchesByCode,
        Collection $customerGroupIds,
        Collection $promotionGroupIds,
        Collection $usersByEmail,
    ): void {
        if (Patient::query()->exists()) {
            return;
        }

        foreach ($this->patientSeedRows() as $row) {
            $branchId = $branchesByCode->get($row['branch_code']);
            if (! is_numeric($branchId)) {
                continue;
            }

            $customer = Customer::query()
                ->where('branch_id', (int) $branchId)
                ->where('phone_search_hash', Customer::phoneSearchHash($row['customer_phone']))
                ->first();

            if (! $customer instanceof Customer) {
                continue;
            }

            $patient = Patient::query()->firstOrNew([
                'customer_id' => $customer->id,
            ]);

            $patient->fill([
                'customer_id' => $customer->id,
                'patient_code' => $patient->patient_code ?: PatientCodeGenerator::generate(),
                'first_branch_id' => (int) $branchId,
                'full_name' => $row['full_name'],
                'email' => $row['email'],
                'email_search_hash' => Patient::emailSearchHash($row['email']),
                'birthday' => $row['birthday'],
                'cccd' => $row['cccd'],
                'gender' => $row['gender'],
                'phone' => $row['phone'],
                'phone_search_hash' => Patient::phoneSearchHash($row['phone']),
                'phone_secondary' => $row['phone_secondary'],
                'occupation' => $row['occupation'],
                'address' => $row['address'],
                'customer_group_id' => $customerGroupIds->get($row['customer_group_code']),
                'promotion_group_id' => $promotionGroupIds->get($row['promotion_group_code']),
                'primary_doctor_id' => $usersByEmail->get($row['primary_doctor_email']),
                'owner_staff_id' => $usersByEmail->get($row['owner_staff_email']),
                'first_visit_reason' => $row['first_visit_reason'],
                'note' => $row['note'],
                'status' => $row['status'],
                'medical_history' => $row['medical_history'],
                'created_by' => $usersByEmail->get('admin@demo.ident.test'),
                'updated_by' => $usersByEmail->get('admin@demo.ident.test'),
            ]);
            $patient->save();

            $customer->update([
                'status' => 'converted',
                'customer_group_id' => $customerGroupIds->get($row['customer_group_code']),
                'promotion_group_id' => $promotionGroupIds->get($row['promotion_group_code']),
                'assigned_to' => $usersByEmail->get($row['owner_staff_email']),
                'updated_by' => $usersByEmail->get('admin@demo.ident.test'),
            ]);
        }
    }

    protected function seedTreatmentJourney(
        Collection $branches,
        Collection $usersByEmail,
        Collection $patientsByPhone,
        Collection $customersByPhone,
    ): void {
        $availableServices = Service::query()
            ->where('active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'default_price']);

        if (! TreatmentPlan::query()->exists() && $availableServices->isNotEmpty()) {
            Patient::query()->orderBy('id')->take(4)->get()->each(function (Patient $patient, int $index) use ($branches, $availableServices): void {
                $doctorId = $patient->primary_doctor_id ?? User::role('Doctor')->orderBy('id')->value('id');
                $selectedServices = $availableServices->slice($index, 3)->values();

                if ($selectedServices->isEmpty()) {
                    $selectedServices = $availableServices->take(2)->values();
                }

                $plan = TreatmentPlan::factory()->create([
                    'patient_id' => $patient->id,
                    'doctor_id' => $doctorId,
                    'branch_id' => $patient->first_branch_id ?? $branches->first()->id,
                    'status' => 'draft',
                    'total_estimated_cost' => 0,
                ]);

                $estimatedTotal = 0.0;

                foreach ($selectedServices as $service) {
                    $item = PlanItem::query()->create([
                        'treatment_plan_id' => $plan->id,
                        'name' => $service->name,
                        'service_id' => $service->id,
                        'quantity' => 1,
                        'price' => $service->default_price,
                        'estimated_supplies' => [],
                    ]);

                    $estimatedTotal += (float) $item->price;

                    TreatmentSession::factory()->create([
                        'treatment_plan_id' => $plan->id,
                        'plan_item_id' => $item->id,
                        'status' => 'scheduled',
                    ]);
                }

                $plan->update(['total_estimated_cost' => $estimatedTotal]);
            });
        }

        $this->seedAppointments($usersByEmail, $patientsByPhone);
        $this->seedCareNotes($usersByEmail, $patientsByPhone, $customersByPhone);
        $this->seedBranchLogs($usersByEmail, $patientsByPhone, $branches);
        $this->seedInvoicesAndPayments($usersByEmail, $patientsByPhone);
        $this->seedReceiptsExpense($usersByEmail, $patientsByPhone);
        $this->seedFactoryOrders($usersByEmail, $patientsByPhone);
        $this->seedZnsCampaigns($usersByEmail, $patientsByPhone, $customersByPhone);
    }

    protected function seedAppointments(Collection $usersByEmail, Collection $patientsByPhone): void
    {
        if (Appointment::query()->exists()) {
            return;
        }

        $definitions = [
            [
                'patient_phone' => '0909123001',
                'doctor_email' => 'doctor.q1@demo.ident.test',
                'assigned_to_email' => 'cskh.q1@demo.ident.test',
                'date' => now()->addDay()->setTime(9, 0),
                'status' => Appointment::STATUS_CONFIRMED,
                'duration_minutes' => 60,
                'appointment_type' => 'consultation',
                'appointment_kind' => 'booking',
                'chief_complaint' => 'Tu van implant rang 46',
                'note' => 'Da xac nhan qua Zalo, can xem phim CT.',
                'confirmed_at' => now()->subHours(3),
                'reminder_hours' => 24,
                'cancellation_reason' => null,
                'reschedule_reason' => null,
            ],
            [
                'patient_phone' => '0909123002',
                'doctor_email' => 'doctor.q1@demo.ident.test',
                'assigned_to_email' => 'cskh.q1@demo.ident.test',
                'date' => now()->subDay()->setTime(18, 30),
                'status' => Appointment::STATUS_COMPLETED,
                'duration_minutes' => 90,
                'appointment_type' => 'treatment',
                'appointment_kind' => 'booking',
                'chief_complaint' => 'Gan rang su tam',
                'note' => 'Benh nhan da chap nhan lich tai kham sau 7 ngay.',
                'confirmed_at' => now()->subDays(2),
                'reminder_hours' => 4,
                'cancellation_reason' => null,
                'reschedule_reason' => null,
            ],
            [
                'patient_phone' => '0912123004',
                'doctor_email' => 'doctor.float@demo.ident.test',
                'assigned_to_email' => 'cskh.cg@demo.ident.test',
                'date' => now()->addDays(2)->setTime(10, 0),
                'status' => Appointment::STATUS_SCHEDULED,
                'duration_minutes' => 45,
                'appointment_type' => 'follow_up',
                'appointment_kind' => 're_exam',
                'chief_complaint' => 'Kham nha chu va cao voi',
                'note' => 'Demo doctor da chi nhanh o Cau Giay.',
                'confirmed_at' => null,
                'reminder_hours' => 24,
                'cancellation_reason' => null,
                'reschedule_reason' => null,
            ],
            [
                'patient_phone' => '0912123005',
                'doctor_email' => 'doctor.cg@demo.ident.test',
                'assigned_to_email' => 'cskh.cg@demo.ident.test',
                'date' => now()->addDays(5)->setTime(14, 30),
                'status' => Appointment::STATUS_RESCHEDULED,
                'duration_minutes' => 60,
                'appointment_type' => 'consultation',
                'appointment_kind' => 'booking',
                'chief_complaint' => 'Chot phac do nieng rang khay trong',
                'note' => 'Khach de nghi doi lich sau hop cong ty.',
                'confirmed_at' => now()->subDay(),
                'reminder_hours' => 24,
                'cancellation_reason' => null,
                'reschedule_reason' => 'Khach ban dot xuat buoi chieu, doi sang thu Hai tuan sau.',
            ],
            [
                'patient_phone' => '0935123007',
                'doctor_email' => 'doctor.hc@demo.ident.test',
                'assigned_to_email' => 'cskh.hc@demo.ident.test',
                'date' => now()->subDays(2)->setTime(16, 0),
                'status' => Appointment::STATUS_NO_SHOW,
                'duration_minutes' => 45,
                'appointment_type' => 'consultation',
                'appointment_kind' => 'booking',
                'chief_complaint' => 'Tay trang truoc dam cuoi',
                'note' => 'No-show, can CSKH goi lai.',
                'confirmed_at' => now()->subDays(3),
                'reminder_hours' => 12,
                'cancellation_reason' => null,
                'reschedule_reason' => null,
            ],
            [
                'patient_phone' => '0935123008',
                'doctor_email' => 'doctor.hc@demo.ident.test',
                'assigned_to_email' => 'cskh.hc@demo.ident.test',
                'date' => now()->addDays(3)->setTime(11, 15),
                'status' => Appointment::STATUS_CANCELLED,
                'duration_minutes' => 90,
                'appointment_type' => 'treatment',
                'appointment_kind' => 'booking',
                'chief_complaint' => 'Danh gia dat tru implant',
                'note' => 'Da huy slot de doi phim X-quang bo sung.',
                'confirmed_at' => now()->subDay(),
                'reminder_hours' => 24,
                'cancellation_reason' => 'Benh nhan can bo sung xet nghiem duong huyet truoc phau thuat.',
                'reschedule_reason' => null,
            ],
        ];

        foreach ($definitions as $definition) {
            $patient = $patientsByPhone->get($definition['patient_phone']);

            if (! $patient instanceof Patient) {
                continue;
            }

            Appointment::query()->create([
                'customer_id' => $patient->customer_id,
                'patient_id' => $patient->id,
                'doctor_id' => $usersByEmail->get($definition['doctor_email']),
                'assigned_to' => $usersByEmail->get($definition['assigned_to_email']),
                'branch_id' => $patient->first_branch_id,
                'date' => $definition['date'],
                'appointment_type' => $definition['appointment_type'],
                'appointment_kind' => $definition['appointment_kind'],
                'duration_minutes' => $definition['duration_minutes'],
                'status' => $definition['status'],
                'note' => $definition['note'],
                'chief_complaint' => $definition['chief_complaint'],
                'cancellation_reason' => $definition['cancellation_reason'],
                'reschedule_reason' => $definition['reschedule_reason'],
                'reminder_hours' => $definition['reminder_hours'],
                'confirmed_at' => $definition['confirmed_at'],
                'confirmed_by' => $usersByEmail->get('admin@demo.ident.test'),
                'is_walk_in' => false,
                'is_emergency' => false,
                'is_overbooked' => false,
            ]);
        }
    }

    protected function seedCareNotes(
        Collection $usersByEmail,
        Collection $patientsByPhone,
        Collection $customersByPhone,
    ): void {
        if (Note::query()->exists()) {
            return;
        }

        $appointmentIdsByPatientId = Appointment::query()
            ->get()
            ->groupBy('patient_id')
            ->map(fn (Collection $appointments): ?int => $appointments->sortByDesc('date')->first()?->id);

        $definitions = [
            [
                'patient_phone' => '0909123001',
                'customer_phone' => '0909123001',
                'user_email' => 'cskh.q1@demo.ident.test',
                'care_type' => 'implant_followup',
                'care_channel' => 'zalo',
                'care_status' => Note::CARE_STATUS_IN_PROGRESS,
                'care_mode' => 'manual',
                'is_recurring' => false,
                'content' => 'Da gui bao gia implant va hen goi lai sau khi benh nhan xem phim CT.',
                'source_type' => Appointment::class,
            ],
            [
                'patient_phone' => '0912123005',
                'customer_phone' => '0912123005',
                'user_email' => 'cskh.cg@demo.ident.test',
                'care_type' => 'orthodontic_followup',
                'care_channel' => 'call',
                'care_status' => Note::CARE_STATUS_NEED_FOLLOWUP,
                'care_mode' => 'manual',
                'is_recurring' => false,
                'content' => 'Khach xin doi lich chot phac do, can nhac lai sau 2 ngay.',
                'source_type' => Patient::class,
            ],
            [
                'patient_phone' => '0935123007',
                'customer_phone' => '0935123007',
                'user_email' => 'cskh.hc@demo.ident.test',
                'care_type' => 'no_show_recovery',
                'care_channel' => 'phone',
                'care_status' => Note::CARE_STATUS_NOT_STARTED,
                'care_mode' => 'automation',
                'is_recurring' => true,
                'content' => 'No-show whitening, can goi lai de xep lich bu toi.',
                'source_type' => Appointment::class,
            ],
            [
                'patient_phone' => null,
                'customer_phone' => '0935123009',
                'user_email' => 'cskh.hc@demo.ident.test',
                'care_type' => 'web_lead_followup',
                'care_channel' => 'zalo',
                'care_status' => Note::CARE_STATUS_DONE,
                'care_mode' => 'manual',
                'is_recurring' => false,
                'content' => 'Lead moi da duoc xac nhan slot lay cao rang sau 18h.',
                'source_type' => Customer::class,
            ],
        ];

        foreach ($definitions as $definition) {
            $patient = filled($definition['patient_phone']) ? $patientsByPhone->get($definition['patient_phone']) : null;
            $customer = $customersByPhone->get($definition['customer_phone']);

            if (! $customer instanceof Customer) {
                continue;
            }

            $sourceId = match ($definition['source_type']) {
                Appointment::class => $patient instanceof Patient ? $appointmentIdsByPatientId->get($patient->id) : null,
                Patient::class => $patient?->id,
                Customer::class => $customer->id,
                default => null,
            };

            Note::query()->create([
                'patient_id' => $patient?->id,
                'branch_id' => $patient?->first_branch_id ?? $customer->branch_id,
                'customer_id' => $customer->id,
                'user_id' => $usersByEmail->get($definition['user_email']),
                'type' => Note::TYPE_GENERAL,
                'care_type' => $definition['care_type'],
                'care_channel' => $definition['care_channel'],
                'care_status' => $definition['care_status'],
                'care_at' => now()->subHours(6),
                'care_mode' => $definition['care_mode'],
                'is_recurring' => $definition['is_recurring'],
                'content' => $definition['content'],
                'source_type' => $definition['source_type'],
                'source_id' => $sourceId,
                'ticket_key' => $sourceId !== null
                    ? Note::ticketKey(
                        $definition['source_type'],
                        $sourceId,
                        $definition['care_type'],
                        (string) ($patient?->first_branch_id ?? $customer->branch_id),
                    )
                    : null,
            ]);
        }
    }

    protected function seedBranchLogs(Collection $usersByEmail, Collection $patientsByPhone, Collection $branches): void
    {
        if (BranchLog::query()->exists()) {
            return;
        }

        $patient = $patientsByPhone->get('0912123004');
        $toBranchId = $branches->firstWhere('code', 'DN-HC')?->id;

        if (! $patient instanceof Patient || ! is_numeric($toBranchId)) {
            return;
        }

        BranchLog::query()->create([
            'patient_id' => $patient->id,
            'from_branch_id' => $patient->first_branch_id,
            'to_branch_id' => (int) $toBranchId,
            'moved_by' => $usersByEmail->get('manager.cg@demo.ident.test'),
            'note' => 'Ban giao benh nhan cho chi nhanh Da Nang de tiep tuc lich phuc hinh khi cong tac dai ngay.',
        ]);
    }

    protected function seedInvoicesAndPayments(Collection $usersByEmail, Collection $patientsByPhone): void
    {
        if (Invoice::query()->exists() || Payment::query()->exists()) {
            return;
        }

        $plansByPatientId = TreatmentPlan::query()->orderBy('id')->get()->keyBy('patient_id');
        $sessionsByPlanId = TreatmentSession::query()
            ->orderBy('id')
            ->get()
            ->groupBy('treatment_plan_id')
            ->map(fn (Collection $sessions): ?TreatmentSession => $sessions->first());

        $invoiceDefinitions = [
            [
                'patient_phone' => '0909123001',
                'invoice_no' => 'INV-DEMO-Q1-001',
                'subtotal' => 18000000,
                'discount_amount' => 1000000,
                'tax_amount' => 0,
                'due_date' => now()->addDays(7)->toDateString(),
                'payments' => [
                    [
                        'amount' => 5000000,
                        'method' => 'transfer',
                        'note' => 'Dat coc implant dot 1',
                        'paid_at' => now()->subDay(),
                    ],
                ],
            ],
            [
                'patient_phone' => '0909123002',
                'invoice_no' => 'INV-DEMO-Q1-002',
                'subtotal' => 9500000,
                'discount_amount' => 500000,
                'tax_amount' => 0,
                'due_date' => now()->addDays(3)->toDateString(),
                'payments' => [
                    [
                        'amount' => 9000000,
                        'method' => 'vnpay',
                        'note' => 'Thanh toan du rang su',
                        'paid_at' => now()->subHours(8),
                    ],
                ],
            ],
            [
                'patient_phone' => '0912123005',
                'invoice_no' => 'INV-DEMO-CG-001',
                'subtotal' => 35000000,
                'discount_amount' => 2000000,
                'tax_amount' => 0,
                'due_date' => now()->subDays(5)->toDateString(),
                'payments' => [],
            ],
            [
                'patient_phone' => '0935123008',
                'invoice_no' => 'INV-DEMO-HC-001',
                'subtotal' => 22000000,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'due_date' => now()->addDays(10)->toDateString(),
                'payments' => [],
            ],
        ];

        foreach ($invoiceDefinitions as $definition) {
            $patient = $patientsByPhone->get($definition['patient_phone']);

            if (! $patient instanceof Patient) {
                continue;
            }

            /** @var TreatmentPlan|null $plan */
            $plan = $plansByPatientId->get($patient->id);
            /** @var TreatmentSession|null $session */
            $session = $plan instanceof TreatmentPlan ? $sessionsByPlanId->get($plan->id) : null;

            $invoice = Invoice::query()->create([
                'treatment_session_id' => $session?->id,
                'treatment_plan_id' => $plan?->id,
                'patient_id' => $patient->id,
                'branch_id' => $patient->first_branch_id,
                'invoice_no' => $definition['invoice_no'],
                'subtotal' => $definition['subtotal'],
                'discount_amount' => $definition['discount_amount'],
                'tax_amount' => $definition['tax_amount'],
                'total_amount' => Invoice::calculateTotalAmount(
                    $definition['subtotal'],
                    $definition['discount_amount'],
                    $definition['tax_amount'],
                ),
                'paid_amount' => 0,
                'status' => Invoice::STATUS_ISSUED,
                'issued_at' => now()->subDays(2),
                'due_date' => $definition['due_date'],
            ]);

            foreach ($definition['payments'] as $paymentDefinition) {
                Payment::query()->create([
                    'invoice_id' => $invoice->id,
                    'branch_id' => $invoice->branch_id,
                    'amount' => $paymentDefinition['amount'],
                    'direction' => 'receipt',
                    'is_deposit' => false,
                    'method' => $paymentDefinition['method'],
                    'transaction_ref' => sprintf('DEMO-%s-%s', $invoice->invoice_no, strtoupper($paymentDefinition['method'])),
                    'payment_source' => 'patient',
                    'paid_at' => $paymentDefinition['paid_at'],
                    'received_by' => $usersByEmail->get('manager.q1@demo.ident.test')
                        ?? $usersByEmail->get('manager.cg@demo.ident.test')
                        ?? $usersByEmail->get('manager.hc@demo.ident.test'),
                    'note' => $paymentDefinition['note'],
                ]);
            }

            $invoice->refresh();
            $invoice->updatePaidAmount();
        }
    }

    protected function seedReceiptsExpense(Collection $usersByEmail, Collection $patientsByPhone): void
    {
        if (! DatabaseSchema::hasTable('receipts_expense') || ReceiptExpense::query()->exists()) {
            return;
        }

        $invoiceIdsByNo = Invoice::query()->pluck('id', 'invoice_no');
        $invoiceBranchIdsByNo = Invoice::query()->pluck('branch_id', 'invoice_no');

        $definitions = [
            [
                'voucher_code' => 'PT-DEMO-Q1-001',
                'patient_phone' => '0909123001',
                'invoice_no' => 'INV-DEMO-Q1-001',
                'voucher_type' => 'receipt',
                'voucher_date' => now()->subDay()->toDateString(),
                'group_code' => 'thu-dieu-tri',
                'category_code' => 'implant-deposit',
                'amount' => 5000000,
                'payment_method' => 'transfer',
                'payer_or_receiver' => 'Nguyen Huu Phuc',
                'content' => 'Thu dot coc implant dot 1 lien ket hoa don demo Quan 1.',
                'status' => 'posted',
                'posted_at' => now()->subDay(),
                'posted_by_email' => 'manager.q1@demo.ident.test',
            ],
            [
                'voucher_code' => 'PC-DEMO-CG-001',
                'patient_phone' => null,
                'invoice_no' => null,
                'branch_code' => 'HN-CG',
                'voucher_type' => 'expense',
                'voucher_date' => now()->subDays(2)->toDateString(),
                'group_code' => 'chi-van-hanh',
                'category_code' => 'vat-tu-tieu-hao',
                'amount' => 1250000,
                'payment_method' => 'cash',
                'payer_or_receiver' => 'Nha cung cap vat tu Cau Giay',
                'content' => 'Chi mua vat tu tieu hao cho khu tri lieu Cau Giay.',
                'status' => 'approved',
                'posted_at' => null,
                'posted_by_email' => null,
            ],
            [
                'voucher_code' => 'PC-DEMO-HC-001',
                'patient_phone' => '0935123008',
                'invoice_no' => null,
                'voucher_type' => 'expense',
                'voucher_date' => now()->toDateString(),
                'group_code' => 'chi-lab',
                'category_code' => 'gia-cong-implant',
                'amount' => 3200000,
                'payment_method' => 'transfer',
                'payer_or_receiver' => 'Lab implant Hai Chau',
                'content' => 'Nháp chi phi lab implant cho ca phuc hinh Da Nang.',
                'status' => 'draft',
                'posted_at' => null,
                'posted_by_email' => null,
            ],
        ];

        foreach ($definitions as $definition) {
            $patient = filled($definition['patient_phone']) ? $patientsByPhone->get($definition['patient_phone']) : null;
            $invoiceId = $definition['invoice_no'] ? $invoiceIdsByNo->get($definition['invoice_no']) : null;
            $clinicId = $patient?->first_branch_id
                ?? (is_numeric($invoiceId) ? $invoiceBranchIdsByNo->get($definition['invoice_no']) : null)
                ?? Branch::query()->where('code', $definition['branch_code'] ?? null)->value('id');

            if (! is_numeric($clinicId)) {
                continue;
            }

            ReceiptExpense::query()->create([
                'clinic_id' => (int) $clinicId,
                'patient_id' => $patient?->id,
                'invoice_id' => is_numeric($invoiceId) ? (int) $invoiceId : null,
                'voucher_code' => $definition['voucher_code'],
                'voucher_type' => $definition['voucher_type'],
                'voucher_date' => $definition['voucher_date'],
                'group_code' => $definition['group_code'],
                'category_code' => $definition['category_code'],
                'amount' => $definition['amount'],
                'payment_method' => $definition['payment_method'],
                'payer_or_receiver' => $definition['payer_or_receiver'],
                'content' => $definition['content'],
                'status' => $definition['status'],
                'posted_at' => $definition['posted_at'],
                'posted_by' => $definition['posted_by_email']
                    ? $usersByEmail->get($definition['posted_by_email'])
                    : null,
            ]);
        }
    }

    protected function seedFactoryOrders(Collection $usersByEmail, Collection $patientsByPhone): void
    {
        if (FactoryOrder::query()->exists()) {
            return;
        }

        $supplierIdsByCode = Supplier::query()->pluck('id', 'code');
        $serviceIdsByName = Service::query()->pluck('id', 'name');

        $definitions = [
            [
                'order_no' => 'FO-DEMO-Q1-001',
                'patient_phone' => '0909123002',
                'doctor_email' => 'doctor.q1@demo.ident.test',
                'requester_email' => 'manager.q1@demo.ident.test',
                'supplier_code' => 'SGDS',
                'status' => FactoryOrder::STATUS_ORDERED,
                'priority' => 'high',
                'due_at' => now()->addDays(6),
                'notes' => 'Rang su zirconia cho 2 rang cua, uu tien giao truoc cuoi tuan.',
                'items' => [
                    [
                        'item_name' => 'Rang su Zirconia 11',
                        'service_name' => 'Bọc răng sứ',
                        'tooth_number' => '11',
                        'material' => 'Zirconia',
                        'shade' => 'A2',
                        'quantity' => 1,
                        'unit_price' => 1800000,
                        'status' => 'ordered',
                        'notes' => 'Can mau sat rang that ben canh.',
                    ],
                ],
            ],
            [
                'order_no' => 'FO-DEMO-HC-001',
                'patient_phone' => '0935123008',
                'doctor_email' => 'doctor.hc@demo.ident.test',
                'requester_email' => 'manager.hc@demo.ident.test',
                'supplier_code' => 'MEDIDN',
                'status' => FactoryOrder::STATUS_IN_PROGRESS,
                'priority' => 'urgent',
                'due_at' => now()->addDays(3),
                'notes' => 'Abutment implant va khung su can doi mau theo phim chup moi nhat.',
                'items' => [
                    [
                        'item_name' => 'Abutment implant 26',
                        'service_name' => 'Implant nha khoa',
                        'tooth_number' => '26',
                        'material' => 'Titan',
                        'shade' => null,
                        'quantity' => 1,
                        'unit_price' => 3200000,
                        'status' => 'in_progress',
                        'notes' => 'Lab dang gia cong phan ket noi.',
                    ],
                ],
            ],
        ];

        foreach ($definitions as $definition) {
            $patient = $patientsByPhone->get($definition['patient_phone']);

            if (! $patient instanceof Patient) {
                continue;
            }

            $order = FactoryOrder::query()->create([
                'order_no' => $definition['order_no'],
                'patient_id' => $patient->id,
                'branch_id' => $patient->first_branch_id,
                'doctor_id' => $usersByEmail->get($definition['doctor_email']),
                'supplier_id' => $supplierIdsByCode->get($definition['supplier_code']),
                'requested_by' => $usersByEmail->get($definition['requester_email']),
                'status' => $definition['status'],
                'priority' => $definition['priority'],
                'ordered_at' => now()->subDay(),
                'due_at' => $definition['due_at'],
                'notes' => $definition['notes'],
            ]);

            foreach ($definition['items'] as $itemDefinition) {
                FactoryOrderItem::query()->create([
                    'factory_order_id' => $order->id,
                    'item_name' => $itemDefinition['item_name'],
                    'service_id' => $serviceIdsByName->get($itemDefinition['service_name']),
                    'tooth_number' => $itemDefinition['tooth_number'],
                    'material' => $itemDefinition['material'],
                    'shade' => $itemDefinition['shade'],
                    'quantity' => $itemDefinition['quantity'],
                    'unit_price' => $itemDefinition['unit_price'],
                    'status' => $itemDefinition['status'],
                    'notes' => $itemDefinition['notes'],
                ]);
            }
        }
    }

    protected function seedZnsCampaigns(
        Collection $usersByEmail,
        Collection $patientsByPhone,
        Collection $customersByPhone,
    ): void {
        if (ZnsCampaign::query()->exists() || ZnsCampaignDelivery::query()->exists()) {
            return;
        }

        $campaignDefinitions = [
            [
                'code' => 'ZNS-DEMO-Q1-RECALL',
                'name' => 'Recall implant Quan 1',
                'branch_id' => $patientsByPhone->get('0909123001')?->first_branch_id,
                'status' => ZnsCampaign::STATUS_COMPLETED,
                'scheduled_at' => now()->subDays(2),
                'started_at' => now()->subDays(2)->addHour(),
                'finished_at' => now()->subDays(2)->addHours(2),
                'sent_count' => 1,
                'failed_count' => 0,
                'template_key' => 'implant_recall',
                'template_id' => 'TPL-IMPLANT-001',
                'deliveries' => [
                    [
                        'patient_phone' => '0909123001',
                        'customer_phone' => '0909123001',
                        'status' => ZnsCampaignDelivery::STATUS_SENT,
                        'provider_message_id' => 'ZNS-MSG-001',
                        'sent_at' => now()->subDays(2)->addHour(),
                    ],
                ],
            ],
            [
                'code' => 'ZNS-DEMO-CG-NOSHOW',
                'name' => 'No-show recovery Cau Giay',
                'branch_id' => $patientsByPhone->get('0912123005')?->first_branch_id,
                'status' => ZnsCampaign::STATUS_SCHEDULED,
                'scheduled_at' => now()->addHours(6),
                'started_at' => null,
                'finished_at' => null,
                'sent_count' => 0,
                'failed_count' => 0,
                'template_key' => 'no_show_recovery',
                'template_id' => 'TPL-RECALL-002',
                'deliveries' => [
                    [
                        'patient_phone' => '0912123005',
                        'customer_phone' => '0912123005',
                        'status' => ZnsCampaignDelivery::STATUS_QUEUED,
                        'provider_message_id' => null,
                        'sent_at' => null,
                    ],
                ],
            ],
        ];

        foreach ($campaignDefinitions as $campaignDefinition) {
            $campaign = ZnsCampaign::query()->create([
                'code' => $campaignDefinition['code'],
                'name' => $campaignDefinition['name'],
                'branch_id' => $campaignDefinition['branch_id'],
                'audience_source' => 'patient_list',
                'audience_last_visit_before_days' => 30,
                'template_key' => $campaignDefinition['template_key'],
                'template_id' => $campaignDefinition['template_id'],
                'audience_payload' => ['scope' => 'demo'],
                'message_payload' => ['scope' => 'demo'],
                'status' => $campaignDefinition['status'],
                'scheduled_at' => $campaignDefinition['scheduled_at'],
                'started_at' => $campaignDefinition['started_at'],
                'finished_at' => $campaignDefinition['finished_at'],
                'sent_count' => $campaignDefinition['sent_count'],
                'failed_count' => $campaignDefinition['failed_count'],
                'created_by' => $usersByEmail->get('admin@demo.ident.test'),
                'updated_by' => $usersByEmail->get('admin@demo.ident.test'),
            ]);

            foreach ($campaignDefinition['deliveries'] as $index => $deliveryDefinition) {
                $patient = $patientsByPhone->get($deliveryDefinition['patient_phone']);
                $customer = $customersByPhone->get($deliveryDefinition['customer_phone']);

                ZnsCampaignDelivery::query()->create([
                    'zns_campaign_id' => $campaign->id,
                    'patient_id' => $patient?->id,
                    'customer_id' => $customer?->id,
                    'branch_id' => $patient?->first_branch_id ?? $customer?->branch_id,
                    'phone' => $patient?->phone ?? $customer?->phone,
                    'normalized_phone' => $patient?->phone ?? $customer?->phone,
                    'idempotency_key' => sprintf('%s-%02d', $campaign->code, $index + 1),
                    'status' => $deliveryDefinition['status'],
                    'attempt_count' => $deliveryDefinition['status'] === ZnsCampaignDelivery::STATUS_QUEUED ? 0 : 1,
                    'provider_message_id' => $deliveryDefinition['provider_message_id'],
                    'provider_status_code' => $deliveryDefinition['status'] === ZnsCampaignDelivery::STATUS_SENT ? '200' : null,
                    'provider_response' => $deliveryDefinition['status'] === ZnsCampaignDelivery::STATUS_SENT
                        ? ['message' => 'accepted']
                        : null,
                    'error_message' => null,
                    'sent_at' => $deliveryDefinition['sent_at'],
                    'next_retry_at' => null,
                    'payload' => ['scope' => 'demo'],
                    'template_key_snapshot' => $campaignDefinition['template_key'],
                    'template_id_snapshot' => $campaignDefinition['template_id'],
                ]);
            }
        }
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    protected function customerSeedRows(): array
    {
        return [
            [
                'branch_code' => 'HCM-Q1',
                'full_name' => 'Nguyen Thi Thu Trang',
                'phone' => '0909123001',
                'email' => 'trang.nguyen01@demo.ident.test',
                'birthday' => '1992-04-12',
                'gender' => 'female',
                'address' => '124 Nguyen Trai, Phuong Ben Thanh, Quan 1, TP.HCM',
                'source' => 'facebook',
                'source_detail' => 'Quang cao implant khu vuc trung tam',
                'customer_group_code' => 'NEW',
                'promotion_group_code' => 'PROMO-IMPL',
                'status' => 'contacted',
                'assigned_to_email' => 'cskh.q1@demo.ident.test',
                'next_follow_up_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
                'notes' => 'Quan tam implant 1 rang ham duoi.',
            ],
            [
                'branch_code' => 'HCM-Q1',
                'full_name' => 'Tran Quoc Huy',
                'phone' => '0909123002',
                'email' => 'huy.tran02@demo.ident.test',
                'birthday' => '1987-11-08',
                'gender' => 'male',
                'address' => '18 Ho Tung Mau, Phuong Ben Nghe, Quan 1, TP.HCM',
                'source' => 'zalo',
                'source_detail' => 'ZNS nhac lich tu chien dich cu',
                'customer_group_code' => 'RETURN',
                'promotion_group_code' => 'PROMO-ESTH',
                'status' => 'confirmed',
                'assigned_to_email' => 'cskh.q1@demo.ident.test',
                'next_follow_up_at' => now()->addDays(4)->format('Y-m-d H:i:s'),
                'notes' => 'Can tu van boc su zirconia 2 rang cua.',
            ],
            [
                'branch_code' => 'HCM-Q1',
                'full_name' => 'Pham Minh Chau',
                'phone' => '0909123003',
                'email' => 'chau.pham03@demo.ident.test',
                'birthday' => '1998-09-21',
                'gender' => 'female',
                'address' => '55 Le Thanh Ton, Phuong Ben Nghe, Quan 1, TP.HCM',
                'source' => 'walkin',
                'source_detail' => 'Khach ghe truc tiep sau gio lam',
                'customer_group_code' => 'NEW',
                'promotion_group_code' => 'PROMO-NEW',
                'status' => 'lead',
                'assigned_to_email' => 'cskh.q1@demo.ident.test',
                'next_follow_up_at' => now()->addDays(1)->format('Y-m-d H:i:s'),
                'notes' => 'Muon dat lich cao voi rang toi thu 7.',
            ],
            [
                'branch_code' => 'HN-CG',
                'full_name' => 'Le Van Nam',
                'phone' => '0912123004',
                'email' => 'nam.le04@demo.ident.test',
                'birthday' => '1984-02-17',
                'gender' => 'male',
                'address' => '16 Duy Tan, Dich Vong Hau, Cau Giay, Ha Noi',
                'source' => 'referral',
                'source_detail' => 'Nguoi nha gioi thieu chinh nha',
                'customer_group_code' => 'FAMILY',
                'promotion_group_code' => 'PROMO-ORTHO',
                'status' => 'contacted',
                'assigned_to_email' => 'cskh.cg@demo.ident.test',
                'next_follow_up_at' => now()->addDays(3)->format('Y-m-d H:i:s'),
                'notes' => 'Gia dinh 2 con nho can kham tong quat.',
            ],
            [
                'branch_code' => 'HN-CG',
                'full_name' => 'Vu Thi Hong Nhung',
                'phone' => '0912123005',
                'email' => 'nhung.vu05@demo.ident.test',
                'birthday' => '1990-06-10',
                'gender' => 'female',
                'address' => '72 Xuan Thuy, Dich Vong Hau, Cau Giay, Ha Noi',
                'source' => 'facebook',
                'source_detail' => 'Lead tu video nieng rang invisalign',
                'customer_group_code' => 'VIP',
                'promotion_group_code' => 'PROMO-ORTHO',
                'status' => 'confirmed',
                'assigned_to_email' => 'cskh.cg@demo.ident.test',
                'next_follow_up_at' => now()->addDays(5)->format('Y-m-d H:i:s'),
                'notes' => 'Da gui bao gia nieng rang khay trong.',
            ],
            [
                'branch_code' => 'HN-CG',
                'full_name' => 'Dang Gia Bao',
                'phone' => '0912123006',
                'email' => 'bao.dang06@demo.ident.test',
                'birthday' => '2001-12-04',
                'gender' => 'male',
                'address' => '25 Nguyen Phong Sac, Dich Vong, Cau Giay, Ha Noi',
                'source' => 'zalo',
                'source_detail' => 'Chat Zalo hoi ve rang khon moc lech',
                'customer_group_code' => 'NEW',
                'promotion_group_code' => 'PROMO-NEW',
                'status' => 'contacted',
                'assigned_to_email' => 'cskh.cg@demo.ident.test',
                'next_follow_up_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
                'notes' => 'Can dat lich chup phim va nho rang khon.',
            ],
            [
                'branch_code' => 'DN-HC',
                'full_name' => 'Hoang Thi Bich Van',
                'phone' => '0935123007',
                'email' => 'van.hoang07@demo.ident.test',
                'birthday' => '1995-03-15',
                'gender' => 'female',
                'address' => '96 Le Duan, Thach Thang, Hai Chau, Da Nang',
                'source' => 'walkin',
                'source_detail' => 'Khach du lich ket hop kham rang',
                'customer_group_code' => 'NEW',
                'promotion_group_code' => 'PROMO-ESTH',
                'status' => 'contacted',
                'assigned_to_email' => 'cskh.hc@demo.ident.test',
                'next_follow_up_at' => now()->addDays(3)->format('Y-m-d H:i:s'),
                'notes' => 'Muon tay trang rang truoc dam cuoi.',
            ],
            [
                'branch_code' => 'DN-HC',
                'full_name' => 'Phan Ngoc Anh',
                'phone' => '0935123008',
                'email' => 'anh.phan08@demo.ident.test',
                'birthday' => '1989-08-19',
                'gender' => 'female',
                'address' => '14 Trung Nu Vuong, Binh Hien, Hai Chau, Da Nang',
                'source' => 'referral',
                'source_detail' => 'Bac si gia dinh gioi thieu',
                'customer_group_code' => 'RETURN',
                'promotion_group_code' => 'PROMO-FAMILY',
                'status' => 'confirmed',
                'assigned_to_email' => 'cskh.hc@demo.ident.test',
                'next_follow_up_at' => now()->addDays(6)->format('Y-m-d H:i:s'),
                'notes' => 'Can lap phac do implant 2 rang ham.',
            ],
            [
                'branch_code' => 'DN-HC',
                'full_name' => 'Nguyen Huu Phuc',
                'phone' => '0935123009',
                'email' => 'phuc.nguyen09@demo.ident.test',
                'birthday' => '1993-01-27',
                'gender' => 'male',
                'address' => '210 Ong Ich Khiem, Thanh Binh, Hai Chau, Da Nang',
                'source' => 'facebook',
                'source_detail' => 'Quan tam lay cao rang dinh ky',
                'customer_group_code' => 'NEW',
                'promotion_group_code' => 'PROMO-NEW',
                'status' => 'lead',
                'assigned_to_email' => 'cskh.hc@demo.ident.test',
                'next_follow_up_at' => now()->addDays(1)->format('Y-m-d H:i:s'),
                'notes' => 'Uu tien lich sau 18h.',
            ],
        ];
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    protected function patientSeedRows(): array
    {
        return [
            [
                'branch_code' => 'HCM-Q1',
                'customer_phone' => '0909123001',
                'full_name' => 'Nguyen Thi Thu Trang',
                'phone' => '0909123001',
                'phone_secondary' => '0909123999',
                'email' => 'trang.nguyen01@demo.ident.test',
                'birthday' => '1992-04-12',
                'cccd' => '079192004512',
                'gender' => 'female',
                'occupation' => 'Nhan vien van phong',
                'address' => '124 Nguyen Trai, Phuong Ben Thanh, Quan 1, TP.HCM',
                'customer_group_code' => 'VIP',
                'promotion_group_code' => 'PROMO-IMPL',
                'primary_doctor_email' => 'doctor.q1@demo.ident.test',
                'owner_staff_email' => 'cskh.q1@demo.ident.test',
                'first_visit_reason' => 'Mat rang ham duoi, can tu van implant',
                'note' => 'Da co phim CT cone beam tu noi khac.',
                'status' => 'active',
                'medical_history' => 'Tien su viem da day, khong di ung thuoc da biet.',
            ],
            [
                'branch_code' => 'HCM-Q1',
                'customer_phone' => '0909123002',
                'full_name' => 'Tran Quoc Huy',
                'phone' => '0909123002',
                'phone_secondary' => null,
                'email' => 'huy.tran02@demo.ident.test',
                'birthday' => '1987-11-08',
                'cccd' => '079187118833',
                'gender' => 'male',
                'occupation' => 'Ky su xay dung',
                'address' => '18 Ho Tung Mau, Phuong Ben Nghe, Quan 1, TP.HCM',
                'customer_group_code' => 'RETURN',
                'promotion_group_code' => 'PROMO-ESTH',
                'primary_doctor_email' => 'doctor.q1@demo.ident.test',
                'owner_staff_email' => 'cskh.q1@demo.ident.test',
                'first_visit_reason' => 'Boc su rang cua bi mon men',
                'note' => 'Can lich tai kham sau 19h.',
                'status' => 'active',
                'medical_history' => 'Hut thuoc nhe, khong benh nen dang dieu tri.',
            ],
            [
                'branch_code' => 'HN-CG',
                'customer_phone' => '0912123004',
                'full_name' => 'Le Van Nam',
                'phone' => '0912123004',
                'phone_secondary' => '0912123666',
                'email' => 'nam.le04@demo.ident.test',
                'birthday' => '1984-02-17',
                'cccd' => '001184021778',
                'gender' => 'male',
                'occupation' => 'Chu doanh nghiep nho',
                'address' => '16 Duy Tan, Dich Vong Hau, Cau Giay, Ha Noi',
                'customer_group_code' => 'FAMILY',
                'promotion_group_code' => 'PROMO-FAMILY',
                'primary_doctor_email' => 'doctor.cg@demo.ident.test',
                'owner_staff_email' => 'cskh.cg@demo.ident.test',
                'first_visit_reason' => 'Cao rang dinh ky va dieu tri vieam nha chu',
                'note' => 'Vo va con cung co nhu cau kham tong quat.',
                'status' => 'active',
                'medical_history' => 'Tang huyet ap da kiem soat bang thuoc.',
            ],
            [
                'branch_code' => 'HN-CG',
                'customer_phone' => '0912123005',
                'full_name' => 'Vu Thi Hong Nhung',
                'phone' => '0912123005',
                'phone_secondary' => null,
                'email' => 'nhung.vu05@demo.ident.test',
                'birthday' => '1990-06-10',
                'cccd' => '001190061045',
                'gender' => 'female',
                'occupation' => 'Truong nhom marketing',
                'address' => '72 Xuan Thuy, Dich Vong Hau, Cau Giay, Ha Noi',
                'customer_group_code' => 'VIP',
                'promotion_group_code' => 'PROMO-ORTHO',
                'primary_doctor_email' => 'doctor.cg@demo.ident.test',
                'owner_staff_email' => 'cskh.cg@demo.ident.test',
                'first_visit_reason' => 'Nieng rang khay trong cho nguoi di lam',
                'note' => 'Can gap doctor de chot phac do va lich thanh toan.',
                'status' => 'active',
                'medical_history' => 'Khong co benh nen dang dieu tri.',
            ],
            [
                'branch_code' => 'DN-HC',
                'customer_phone' => '0935123007',
                'full_name' => 'Hoang Thi Bich Van',
                'phone' => '0935123007',
                'phone_secondary' => null,
                'email' => 'van.hoang07@demo.ident.test',
                'birthday' => '1995-03-15',
                'cccd' => '201195031501',
                'gender' => 'female',
                'occupation' => 'Chuyen vien nhan su',
                'address' => '96 Le Duan, Thach Thang, Hai Chau, Da Nang',
                'customer_group_code' => 'NEW',
                'promotion_group_code' => 'PROMO-ESTH',
                'primary_doctor_email' => 'doctor.hc@demo.ident.test',
                'owner_staff_email' => 'cskh.hc@demo.ident.test',
                'first_visit_reason' => 'Tay trang rang va kiem tra men rang',
                'note' => 'Muon chup anh truoc sau de so sanh.',
                'status' => 'active',
                'medical_history' => 'Da tung e buot rang, can luu y khi tay trang.',
            ],
            [
                'branch_code' => 'DN-HC',
                'customer_phone' => '0935123008',
                'full_name' => 'Phan Ngoc Anh',
                'phone' => '0935123008',
                'phone_secondary' => '0935123888',
                'email' => 'anh.phan08@demo.ident.test',
                'birthday' => '1989-08-19',
                'cccd' => '201189081945',
                'gender' => 'female',
                'occupation' => 'Ke toan truong',
                'address' => '14 Trung Nu Vuong, Binh Hien, Hai Chau, Da Nang',
                'customer_group_code' => 'RETURN',
                'promotion_group_code' => 'PROMO-IMPL',
                'primary_doctor_email' => 'doctor.hc@demo.ident.test',
                'owner_staff_email' => 'cskh.hc@demo.ident.test',
                'first_visit_reason' => 'Phuc hinh implant hai rang ham tren',
                'note' => 'Can bao gia tach theo giai doan dieu tri.',
                'status' => 'active',
                'medical_history' => 'Khong di ung penicillin, co tien su tieu duong thai ky.',
            ],
        ];
    }
}
