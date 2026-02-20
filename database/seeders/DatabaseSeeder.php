<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\BranchLog;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Material;
use App\Models\Note;
use App\Models\Patient;
use App\Models\PlanItem;
use App\Models\PromotionGroup;
use App\Models\Service;
use App\Models\TreatmentPlan;
use App\Models\TreatmentSession;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            SystemSettingsSeeder::class,
        ]);

        // Branches
        $branches = Branch::factory()->count(2)->create();

        // Super Admin
        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'branch_id' => $branches->first()->id,
            ],
        );
        $admin->syncRoles(['Admin']);

        // Manager, Doctor, CSKH
        $roles = ['Manager', 'Doctor', 'CSKH'];
        foreach ($roles as $i => $roleName) {
            $user = User::updateOrCreate(
                ['email' => strtolower($roleName) . '@example.com'],
                [
                    'name' => $roleName . ' User',
                    'password' => Hash::make('password'),
                    'branch_id' => $branches[$i % $branches->count()]->id,
                ],
            );
            $user->syncRoles([$roleName]);
        }

        // Domain seeds (VI): Vật tư cơ bản theo chi nhánh
        $baseMaterials = [
            ['name' => 'Găng tay y tế', 'unit' => 'hộp', 'unit_price' => 75000, 'min_stock' => 5],
            ['name' => 'Khẩu trang', 'unit' => 'hộp', 'unit_price' => 45000, 'min_stock' => 5],
            ['name' => 'Chỉ nha khoa', 'unit' => 'cuộn', 'unit_price' => 30000, 'min_stock' => 10],
            ['name' => 'Composite', 'unit' => 'ống', 'unit_price' => 120000, 'min_stock' => 3],
            ['name' => 'Cement', 'unit' => 'gói', 'unit_price' => 85000, 'min_stock' => 2],
            ['name' => 'Kim tiêm', 'unit' => 'cái', 'unit_price' => 4000, 'min_stock' => 50],
            ['name' => 'Thuốc tê', 'unit' => 'ống', 'unit_price' => 30000, 'min_stock' => 10],
            ['name' => 'Bông gạc', 'unit' => 'túi', 'unit_price' => 20000, 'min_stock' => 10],
        ];
        foreach ($branches as $branch) {
            foreach ($baseMaterials as $i => $m) {
                Material::updateOrCreate(
                    ['branch_id' => $branch->id, 'name' => $m['name']],
                    [
                        'sku' => 'VT-' . $branch->id . '-' . Str::padLeft((string) ($i + 1), 3, '0'),
                        'unit' => $m['unit'],
                        'stock_qty' => rand(20, 100),
                        'sale_price' => $m['unit_price'],
                        'min_stock' => $m['min_stock'],
                    ]
                );
            }
        }

        // Create additional doctors as Users with Doctor role if needed
        if (\App\Models\User::role('Doctor')->count() < 3) {
            for ($i = 0; $i < 3; $i++) {
                $doc = \App\Models\User::factory()->create([
                    'branch_id' => $branches[$i % $branches->count()]->id,
                ]);
                $doc->assignRole('Doctor');
            }
        }

        $doctorIds = User::role('Doctor')->pluck('id');
        $ownerStaffIds = User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['CSKH', 'Manager']))
            ->pluck('id');
        $customerGroupIds = CustomerGroup::query()
            ->where('is_active', true)
            ->pluck('id');
        $promotionGroupIds = PromotionGroup::query()
            ->where('is_active', true)
            ->pluck('id');

        // Leads
        $customers = Customer::factory()->count(24)->create();
        $customers->each(function (Customer $customer) use ($customerGroupIds, $promotionGroupIds, $ownerStaffIds, $admin): void {
            $customerGroupId = $customerGroupIds->isNotEmpty() ? $customerGroupIds->random() : null;
            $promotionGroupId = $promotionGroupIds->isNotEmpty() && fake()->boolean(45)
                ? $promotionGroupIds->random()
                : null;

            $customer->update([
                'birthday' => fake()->optional(0.75)->dateTimeBetween('-60 years', '-18 years')?->format('Y-m-d'),
                'gender' => fake()->randomElement(['male', 'female', 'other']),
                'address' => fake()->address(),
                'customer_group_id' => $customerGroupId,
                'promotion_group_id' => $promotionGroupId,
                'assigned_to' => $ownerStaffIds->isNotEmpty() ? $ownerStaffIds->random() : null,
                'next_follow_up_at' => fake()->optional(0.6)->dateTimeBetween('now', '+21 days')?->format('Y-m-d'),
                'status' => fake()->randomElement(['lead', 'contacted', 'confirmed']),
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]);
        });

        // Patients
        $patients = Patient::factory()->count(14)->create();
        $occupations = [
            'Nhân viên văn phòng',
            'Kinh doanh tự do',
            'Giáo viên',
            'Kỹ sư',
            'Sinh viên',
            'Nội trợ',
            'Bác sĩ',
            'Tài xế',
        ];
        $firstVisitReasons = [
            'Đau răng kéo dài',
            'Tư vấn trồng Implant',
            'Niềng răng thẩm mỹ',
            'Khám tổng quát định kỳ',
            'Tẩy trắng răng',
            'Nhổ răng khôn',
        ];

        $patients->each(function (Patient $patient) use ($customerGroupIds, $promotionGroupIds, $doctorIds, $ownerStaffIds, $firstVisitReasons, $occupations, $admin): void {
            $linkedCustomer = $patient->customer;

            $customerGroupId = $linkedCustomer?->customer_group_id
                ?: ($customerGroupIds->isNotEmpty() ? $customerGroupIds->random() : null);
            $promotionGroupId = $linkedCustomer?->promotion_group_id
                ?: ($promotionGroupIds->isNotEmpty() && fake()->boolean(40) ? $promotionGroupIds->random() : null);

            $patient->update([
                'birthday' => $patient->birthday ?? fake()->dateTimeBetween('-70 years', '-6 years')?->format('Y-m-d'),
                'cccd' => fake()->numerify('############'),
                'phone_secondary' => fake()->optional(0.45)->numerify('09########'),
                'occupation' => fake()->randomElement($occupations),
                'customer_group_id' => $customerGroupId,
                'promotion_group_id' => $promotionGroupId,
                'primary_doctor_id' => $doctorIds->isNotEmpty() ? $doctorIds->random() : null,
                'owner_staff_id' => $ownerStaffIds->isNotEmpty() ? $ownerStaffIds->random() : null,
                'first_visit_reason' => fake()->randomElement($firstVisitReasons),
                'note' => fake()->optional(0.7)->sentence(),
                'status' => fake()->randomElement(['active', 'active', 'active', 'inactive']),
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]);

            if ($linkedCustomer) {
                $linkedCustomer->update([
                    'status' => 'converted',
                    'customer_group_id' => $customerGroupId,
                    'promotion_group_id' => $promotionGroupId,
                    'assigned_to' => $patient->owner_staff_id,
                    'updated_by' => $admin->id,
                ]);
            }
        });

        // Appointments
        Appointment::factory()->count(15)->create();

        $availableServices = Service::query()
            ->where('active', true)
            ->get(['id', 'name', 'default_price']);

        if ($availableServices->isEmpty()) {
            $fallbackServices = [
                ['name' => 'Nhổ răng khôn', 'code' => 'NHO-RANG-KHON', 'unit' => 'lần', 'default_price' => 1500000],
                ['name' => 'Trám răng', 'code' => 'TRAM-RANG', 'unit' => 'răng', 'default_price' => 500000],
                ['name' => 'Điều trị tủy', 'code' => 'DIEU-TRI-TUY', 'unit' => 'răng', 'default_price' => 1200000],
            ];

            foreach ($fallbackServices as $service) {
                Service::updateOrCreate(
                    ['code' => $service['code']],
                    $service + ['active' => true],
                );
            }

            $availableServices = Service::query()
                ->where('active', true)
                ->get(['id', 'name', 'default_price']);
        }

        // Treatment Plans (VI): Kế hoạch + hạng mục + buổi điều trị với dữ liệu gần thực tế
        $patientsForPlans = Patient::query()->inRandomOrder()->take(6)->get();
        foreach ($patientsForPlans as $patient) {
            $plan = TreatmentPlan::factory()->create([
                'patient_id' => $patient->id,
                'doctor_id' => User::role('Doctor')->inRandomOrder()->value('id'),
                'branch_id' => $patient->first_branch_id ?? $branches->first()->id,
                'status' => 'draft',
                'total_estimated_cost' => 0,
            ]);

            $itemCount = min($availableServices->count(), random_int(2, 4));
            $selectedServices = $availableServices->shuffle()->take(max($itemCount, 1));

            $estimatedTotal = 0.0;
            foreach ($selectedServices as $service) {
                $item = PlanItem::create([
                    'treatment_plan_id' => $plan->id,
                    'name' => $service->name,
                    'service_id' => $service->id,
                    'quantity' => 1,
                    'price' => $service->default_price,
                    'estimated_supplies' => [],
                ]);

                $estimatedTotal += (float) $item->price;

                // One scheduled session per item (no inventory yet)
                TreatmentSession::factory()->create([
                    'treatment_plan_id' => $plan->id,
                    'plan_item_id' => $item->id,
                    'status' => 'scheduled',
                ]);
            }
            $plan->update(['total_estimated_cost' => $estimatedTotal]);
        }

        // Notes
        Note::factory()->count(20)->create();

        // Branch movement logs
        BranchLog::factory()->count(5)->create();
    }
}
