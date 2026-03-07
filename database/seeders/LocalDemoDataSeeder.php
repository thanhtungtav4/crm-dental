<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\BranchLog;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Note;
use App\Models\Patient;
use App\Models\PlanItem;
use App\Models\PromotionGroup;
use App\Models\Service;
use App\Models\TreatmentPlan;
use App\Models\TreatmentSession;
use App\Models\User;
use App\Support\PatientCodeGenerator;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

class LocalDemoDataSeeder extends Seeder
{
    use WithoutModelEvents;

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
        $this->seedTreatmentJourney($branches);
    }

    protected function seedBranches(): Collection
    {
        $definitions = [
            [
                'code' => 'HCM-Q1',
                'name' => 'Nha khoa An Phuc Quan 1',
                'address' => '35 Nguyen Binh Khiem, Ben Nghe, Quan 1, TP.HCM',
                'phone' => '02836225501',
                'active' => true,
            ],
            [
                'code' => 'HN-CG',
                'name' => 'Nha khoa An Phuc Cau Giay',
                'address' => '88 Tran Thai Tong, Dich Vong Hau, Cau Giay, Ha Noi',
                'phone' => '02437668801',
                'active' => true,
            ],
            [
                'code' => 'DN-HC',
                'name' => 'Nha khoa An Phuc Hai Chau',
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
                'email' => 'admin@demo.nhakhoaanphuc.test',
                'name' => 'Quan tri he thong',
                'password' => Hash::make('password'),
                'branch_id' => $branchIdsByCode['HCM-Q1'],
                'role' => 'Admin',
            ],
            [
                'email' => 'manager.q1@demo.nhakhoaanphuc.test',
                'name' => 'Quan ly Quan 1',
                'password' => Hash::make('password'),
                'branch_id' => $branchIdsByCode['HCM-Q1'],
                'role' => 'Manager',
            ],
            [
                'email' => 'doctor.q1@demo.nhakhoaanphuc.test',
                'name' => 'Bac si Tran Minh Khoi',
                'password' => Hash::make('password'),
                'branch_id' => $branchIdsByCode['HCM-Q1'],
                'role' => 'Doctor',
            ],
            [
                'email' => 'doctor.cg@demo.nhakhoaanphuc.test',
                'name' => 'Bac si Nguyen Ngoc Lan',
                'password' => Hash::make('password'),
                'branch_id' => $branchIdsByCode['HN-CG'],
                'role' => 'Doctor',
            ],
            [
                'email' => 'doctor.hc@demo.nhakhoaanphuc.test',
                'name' => 'Bac si Le Quoc Bao',
                'password' => Hash::make('password'),
                'branch_id' => $branchIdsByCode['DN-HC'],
                'role' => 'Doctor',
            ],
            [
                'email' => 'cskh.q1@demo.nhakhoaanphuc.test',
                'name' => 'CSKH Pham Thu Ha',
                'password' => Hash::make('password'),
                'branch_id' => $branchIdsByCode['HCM-Q1'],
                'role' => 'CSKH',
            ],
            [
                'email' => 'cskh.cg@demo.nhakhoaanphuc.test',
                'name' => 'CSKH Vo Thao My',
                'password' => Hash::make('password'),
                'branch_id' => $branchIdsByCode['HN-CG'],
                'role' => 'CSKH',
            ],
            [
                'email' => 'cskh.hc@demo.nhakhoaanphuc.test',
                'name' => 'CSKH Nguyen Bao Tram',
                'password' => Hash::make('password'),
                'branch_id' => $branchIdsByCode['DN-HC'],
                'role' => 'CSKH',
            ],
        ];

        /** @var User|null $admin */
        $admin = null;

        foreach ($accounts as $account) {
            $role = $account['role'];
            unset($account['role']);

            $user = User::query()->updateOrCreate(
                ['email' => $account['email']],
                $account,
            );

            $user->syncRoles([$role]);

            if ($role === 'Admin') {
                $admin = $user;
            }
        }

        return $admin ?? User::query()->where('email', 'admin@demo.nhakhoaanphuc.test')->firstOrFail();
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
                'created_by' => $usersByEmail->get('admin@demo.nhakhoaanphuc.test'),
                'updated_by' => $usersByEmail->get('admin@demo.nhakhoaanphuc.test'),
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
                'created_by' => $usersByEmail->get('admin@demo.nhakhoaanphuc.test'),
                'updated_by' => $usersByEmail->get('admin@demo.nhakhoaanphuc.test'),
            ]);
            $patient->save();

            $customer->update([
                'status' => 'converted',
                'customer_group_id' => $customerGroupIds->get($row['customer_group_code']),
                'promotion_group_id' => $promotionGroupIds->get($row['promotion_group_code']),
                'assigned_to' => $usersByEmail->get($row['owner_staff_email']),
                'updated_by' => $usersByEmail->get('admin@demo.nhakhoaanphuc.test'),
            ]);
        }
    }

    protected function seedTreatmentJourney(Collection $branches): void
    {
        if (
            Appointment::query()->exists()
            || TreatmentPlan::query()->exists()
            || Note::query()->exists()
            || BranchLog::query()->exists()
        ) {
            return;
        }

        Appointment::factory()->count(10)->create();

        $availableServices = Service::query()
            ->where('active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'default_price']);

        if ($availableServices->isEmpty()) {
            return;
        }

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

        Note::factory()->count(12)->create();
        BranchLog::factory()->count(3)->create();
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
                'email' => 'trang.nguyen01@demo.nhakhoaanphuc.test',
                'birthday' => '1992-04-12',
                'gender' => 'female',
                'address' => '124 Nguyen Trai, Phuong Ben Thanh, Quan 1, TP.HCM',
                'source' => 'facebook',
                'source_detail' => 'Quang cao implant khu vuc trung tam',
                'customer_group_code' => 'NEW',
                'promotion_group_code' => 'PROMO-IMPL',
                'status' => 'contacted',
                'assigned_to_email' => 'cskh.q1@demo.nhakhoaanphuc.test',
                'next_follow_up_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
                'notes' => 'Quan tam implant 1 rang ham duoi.',
            ],
            [
                'branch_code' => 'HCM-Q1',
                'full_name' => 'Tran Quoc Huy',
                'phone' => '0909123002',
                'email' => 'huy.tran02@demo.nhakhoaanphuc.test',
                'birthday' => '1987-11-08',
                'gender' => 'male',
                'address' => '18 Ho Tung Mau, Phuong Ben Nghe, Quan 1, TP.HCM',
                'source' => 'zalo',
                'source_detail' => 'ZNS nhac lich tu chien dich cu',
                'customer_group_code' => 'RETURN',
                'promotion_group_code' => 'PROMO-ESTH',
                'status' => 'confirmed',
                'assigned_to_email' => 'cskh.q1@demo.nhakhoaanphuc.test',
                'next_follow_up_at' => now()->addDays(4)->format('Y-m-d H:i:s'),
                'notes' => 'Can tu van boc su zirconia 2 rang cua.',
            ],
            [
                'branch_code' => 'HCM-Q1',
                'full_name' => 'Pham Minh Chau',
                'phone' => '0909123003',
                'email' => 'chau.pham03@demo.nhakhoaanphuc.test',
                'birthday' => '1998-09-21',
                'gender' => 'female',
                'address' => '55 Le Thanh Ton, Phuong Ben Nghe, Quan 1, TP.HCM',
                'source' => 'walkin',
                'source_detail' => 'Khach ghe truc tiep sau gio lam',
                'customer_group_code' => 'NEW',
                'promotion_group_code' => 'PROMO-NEW',
                'status' => 'lead',
                'assigned_to_email' => 'cskh.q1@demo.nhakhoaanphuc.test',
                'next_follow_up_at' => now()->addDays(1)->format('Y-m-d H:i:s'),
                'notes' => 'Muon dat lich cao voi rang toi thu 7.',
            ],
            [
                'branch_code' => 'HN-CG',
                'full_name' => 'Le Van Nam',
                'phone' => '0912123004',
                'email' => 'nam.le04@demo.nhakhoaanphuc.test',
                'birthday' => '1984-02-17',
                'gender' => 'male',
                'address' => '16 Duy Tan, Dich Vong Hau, Cau Giay, Ha Noi',
                'source' => 'referral',
                'source_detail' => 'Nguoi nha gioi thieu chinh nha',
                'customer_group_code' => 'FAMILY',
                'promotion_group_code' => 'PROMO-ORTHO',
                'status' => 'contacted',
                'assigned_to_email' => 'cskh.cg@demo.nhakhoaanphuc.test',
                'next_follow_up_at' => now()->addDays(3)->format('Y-m-d H:i:s'),
                'notes' => 'Gia dinh 2 con nho can kham tong quat.',
            ],
            [
                'branch_code' => 'HN-CG',
                'full_name' => 'Vu Thi Hong Nhung',
                'phone' => '0912123005',
                'email' => 'nhung.vu05@demo.nhakhoaanphuc.test',
                'birthday' => '1990-06-10',
                'gender' => 'female',
                'address' => '72 Xuan Thuy, Dich Vong Hau, Cau Giay, Ha Noi',
                'source' => 'facebook',
                'source_detail' => 'Lead tu video nieng rang invisalign',
                'customer_group_code' => 'VIP',
                'promotion_group_code' => 'PROMO-ORTHO',
                'status' => 'confirmed',
                'assigned_to_email' => 'cskh.cg@demo.nhakhoaanphuc.test',
                'next_follow_up_at' => now()->addDays(5)->format('Y-m-d H:i:s'),
                'notes' => 'Da gui bao gia nieng rang khay trong.',
            ],
            [
                'branch_code' => 'HN-CG',
                'full_name' => 'Dang Gia Bao',
                'phone' => '0912123006',
                'email' => 'bao.dang06@demo.nhakhoaanphuc.test',
                'birthday' => '2001-12-04',
                'gender' => 'male',
                'address' => '25 Nguyen Phong Sac, Dich Vong, Cau Giay, Ha Noi',
                'source' => 'zalo',
                'source_detail' => 'Chat Zalo hoi ve rang khon moc lech',
                'customer_group_code' => 'NEW',
                'promotion_group_code' => 'PROMO-NEW',
                'status' => 'lead',
                'assigned_to_email' => 'cskh.cg@demo.nhakhoaanphuc.test',
                'next_follow_up_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
                'notes' => 'Can dat lich chup phim va nho rang khon.',
            ],
            [
                'branch_code' => 'DN-HC',
                'full_name' => 'Hoang Thi Bich Van',
                'phone' => '0935123007',
                'email' => 'van.hoang07@demo.nhakhoaanphuc.test',
                'birthday' => '1995-03-15',
                'gender' => 'female',
                'address' => '96 Le Duan, Thach Thang, Hai Chau, Da Nang',
                'source' => 'walkin',
                'source_detail' => 'Khach du lich ket hop kham rang',
                'customer_group_code' => 'NEW',
                'promotion_group_code' => 'PROMO-ESTH',
                'status' => 'contacted',
                'assigned_to_email' => 'cskh.hc@demo.nhakhoaanphuc.test',
                'next_follow_up_at' => now()->addDays(3)->format('Y-m-d H:i:s'),
                'notes' => 'Muon tay trang rang truoc dam cuoi.',
            ],
            [
                'branch_code' => 'DN-HC',
                'full_name' => 'Phan Ngoc Anh',
                'phone' => '0935123008',
                'email' => 'anh.phan08@demo.nhakhoaanphuc.test',
                'birthday' => '1989-08-19',
                'gender' => 'female',
                'address' => '14 Trung Nu Vuong, Binh Hien, Hai Chau, Da Nang',
                'source' => 'referral',
                'source_detail' => 'Bac si gia dinh gioi thieu',
                'customer_group_code' => 'RETURN',
                'promotion_group_code' => 'PROMO-FAMILY',
                'status' => 'confirmed',
                'assigned_to_email' => 'cskh.hc@demo.nhakhoaanphuc.test',
                'next_follow_up_at' => now()->addDays(6)->format('Y-m-d H:i:s'),
                'notes' => 'Can lap phac do implant 2 rang ham.',
            ],
            [
                'branch_code' => 'DN-HC',
                'full_name' => 'Nguyen Huu Phuc',
                'phone' => '0935123009',
                'email' => 'phuc.nguyen09@demo.nhakhoaanphuc.test',
                'birthday' => '1993-01-27',
                'gender' => 'male',
                'address' => '210 Ong Ich Khiem, Thanh Binh, Hai Chau, Da Nang',
                'source' => 'facebook',
                'source_detail' => 'Quan tam lay cao rang dinh ky',
                'customer_group_code' => 'NEW',
                'promotion_group_code' => 'PROMO-NEW',
                'status' => 'lead',
                'assigned_to_email' => 'cskh.hc@demo.nhakhoaanphuc.test',
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
                'email' => 'trang.nguyen01@demo.nhakhoaanphuc.test',
                'birthday' => '1992-04-12',
                'cccd' => '079192004512',
                'gender' => 'female',
                'occupation' => 'Nhan vien van phong',
                'address' => '124 Nguyen Trai, Phuong Ben Thanh, Quan 1, TP.HCM',
                'customer_group_code' => 'VIP',
                'promotion_group_code' => 'PROMO-IMPL',
                'primary_doctor_email' => 'doctor.q1@demo.nhakhoaanphuc.test',
                'owner_staff_email' => 'cskh.q1@demo.nhakhoaanphuc.test',
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
                'email' => 'huy.tran02@demo.nhakhoaanphuc.test',
                'birthday' => '1987-11-08',
                'cccd' => '079187118833',
                'gender' => 'male',
                'occupation' => 'Ky su xay dung',
                'address' => '18 Ho Tung Mau, Phuong Ben Nghe, Quan 1, TP.HCM',
                'customer_group_code' => 'RETURN',
                'promotion_group_code' => 'PROMO-ESTH',
                'primary_doctor_email' => 'doctor.q1@demo.nhakhoaanphuc.test',
                'owner_staff_email' => 'cskh.q1@demo.nhakhoaanphuc.test',
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
                'email' => 'nam.le04@demo.nhakhoaanphuc.test',
                'birthday' => '1984-02-17',
                'cccd' => '001184021778',
                'gender' => 'male',
                'occupation' => 'Chu doanh nghiep nho',
                'address' => '16 Duy Tan, Dich Vong Hau, Cau Giay, Ha Noi',
                'customer_group_code' => 'FAMILY',
                'promotion_group_code' => 'PROMO-FAMILY',
                'primary_doctor_email' => 'doctor.cg@demo.nhakhoaanphuc.test',
                'owner_staff_email' => 'cskh.cg@demo.nhakhoaanphuc.test',
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
                'email' => 'nhung.vu05@demo.nhakhoaanphuc.test',
                'birthday' => '1990-06-10',
                'cccd' => '001190061045',
                'gender' => 'female',
                'occupation' => 'Truong nhom marketing',
                'address' => '72 Xuan Thuy, Dich Vong Hau, Cau Giay, Ha Noi',
                'customer_group_code' => 'VIP',
                'promotion_group_code' => 'PROMO-ORTHO',
                'primary_doctor_email' => 'doctor.cg@demo.nhakhoaanphuc.test',
                'owner_staff_email' => 'cskh.cg@demo.nhakhoaanphuc.test',
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
                'email' => 'van.hoang07@demo.nhakhoaanphuc.test',
                'birthday' => '1995-03-15',
                'cccd' => '201195031501',
                'gender' => 'female',
                'occupation' => 'Chuyen vien nhan su',
                'address' => '96 Le Duan, Thach Thang, Hai Chau, Da Nang',
                'customer_group_code' => 'NEW',
                'promotion_group_code' => 'PROMO-ESTH',
                'primary_doctor_email' => 'doctor.hc@demo.nhakhoaanphuc.test',
                'owner_staff_email' => 'cskh.hc@demo.nhakhoaanphuc.test',
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
                'email' => 'anh.phan08@demo.nhakhoaanphuc.test',
                'birthday' => '1989-08-19',
                'cccd' => '201189081945',
                'gender' => 'female',
                'occupation' => 'Ke toan truong',
                'address' => '14 Trung Nu Vuong, Binh Hien, Hai Chau, Da Nang',
                'customer_group_code' => 'RETURN',
                'promotion_group_code' => 'PROMO-IMPL',
                'primary_doctor_email' => 'doctor.hc@demo.nhakhoaanphuc.test',
                'owner_staff_email' => 'cskh.hc@demo.nhakhoaanphuc.test',
                'first_visit_reason' => 'Phuc hinh implant hai rang ham tren',
                'note' => 'Can bao gia tach theo giai doan dieu tri.',
                'status' => 'active',
                'medical_history' => 'Khong di ung penicillin, co tien su tieu duong thai ky.',
            ],
        ];
    }
}
