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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            SystemSettingsSeeder::class,
            CustomerAndPromotionGroupsSeeder::class,
            ServiceCategoriesAndServicesSeeder::class,
            ClinicSettingsSeeder::class,
        ]);

        $branches = Branch::factory()->count(2)->create();

        $this->call([
            InventorySeeder::class,
        ]);
        $admin = $this->seedUsers($branches);

        $this->seedMaterialsByBranch($branches);

        $doctorIds = User::role('Doctor')->pluck('id');
        $ownerStaffIds = User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['CSKH', 'Manager']))
            ->pluck('id');

        $customerGroupIds = CustomerGroup::query()->where('is_active', true)->pluck('id');
        $promotionGroupIds = PromotionGroup::query()->where('is_active', true)->pluck('id');

        $this->seedCustomers($admin->id, $customerGroupIds, $promotionGroupIds, $ownerStaffIds);
        $this->seedPatients($admin->id, $customerGroupIds, $promotionGroupIds, $doctorIds, $ownerStaffIds);
        $this->seedTreatmentJourney($branches);
    }

    protected function seedUsers(Collection $branches): User
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'branch_id' => $branches->first()->id,
            ],
        );

        $admin->syncRoles(['Admin']);

        foreach (['Manager', 'Doctor', 'CSKH'] as $index => $roleName) {
            $user = User::updateOrCreate(
                ['email' => strtolower($roleName) . '@example.com'],
                [
                    'name' => $roleName . ' User',
                    'password' => Hash::make('password'),
                    'branch_id' => $branches[$index % $branches->count()]->id,
                ],
            );

            $user->syncRoles([$roleName]);
        }

        if (User::role('Doctor')->count() < 3) {
            for ($index = 0; $index < 3; $index++) {
                $doctor = User::factory()->create([
                    'branch_id' => $branches[$index % $branches->count()]->id,
                ]);

                $doctor->assignRole('Doctor');
            }
        }

        return $admin;
    }

    protected function seedMaterialsByBranch(Collection $branches): void
    {
        $baseMaterials = [
            ['name' => 'Găng tay nitrile', 'unit' => 'hộp', 'sale_price' => 195000, 'min_stock' => 20, 'stock_range' => [25, 80]],
            ['name' => 'Khẩu trang y tế 4 lớp', 'unit' => 'hộp', 'sale_price' => 125000, 'min_stock' => 20, 'stock_range' => [30, 100]],
            ['name' => 'Chỉ nha khoa', 'unit' => 'cuộn', 'sale_price' => 42000, 'min_stock' => 15, 'stock_range' => [20, 60]],
            ['name' => 'Composite trám răng', 'unit' => 'ống', 'sale_price' => 340000, 'min_stock' => 6, 'stock_range' => [8, 28]],
            ['name' => 'Xi măng gắn răng', 'unit' => 'gói', 'sale_price' => 98000, 'min_stock' => 8, 'stock_range' => [12, 45]],
            ['name' => 'Kim tiêm 27G', 'unit' => 'hộp', 'sale_price' => 265000, 'min_stock' => 12, 'stock_range' => [15, 50]],
            ['name' => 'Thuốc tê Articaine 4%', 'unit' => 'hộp', 'sale_price' => 780000, 'min_stock' => 4, 'stock_range' => [5, 22]],
            ['name' => 'Gạc vô trùng', 'unit' => 'gói', 'sale_price' => 62000, 'min_stock' => 15, 'stock_range' => [18, 75]],
        ];

        foreach ($branches as $branch) {
            foreach ($baseMaterials as $index => $material) {
                Material::updateOrCreate(
                    ['branch_id' => $branch->id, 'name' => $material['name']],
                    [
                        'sku' => 'VT-' . $branch->id . '-' . Str::padLeft((string) ($index + 1), 3, '0'),
                        'unit' => $material['unit'],
                        'stock_qty' => random_int($material['stock_range'][0], $material['stock_range'][1]),
                        'sale_price' => $material['sale_price'],
                        'min_stock' => $material['min_stock'],
                    ],
                );
            }
        }
    }

    protected function seedCustomers(
        int $adminId,
        Collection $customerGroupIds,
        Collection $promotionGroupIds,
        Collection $ownerStaffIds,
    ): void {
        $customerStatuses = ['lead', 'lead', 'contacted', 'contacted', 'confirmed'];

        Customer::factory()->count(24)->create()->each(function (Customer $customer) use ($adminId, $customerGroupIds, $promotionGroupIds, $ownerStaffIds, $customerStatuses): void {
            $customer->update([
                'birthday' => fake()->optional(0.8)->dateTimeBetween('-60 years', '-18 years')?->format('Y-m-d'),
                'gender' => fake()->randomElement(['male', 'female']),
                'address' => fake('vi_VN')->address(),
                'customer_group_id' => $customerGroupIds->isNotEmpty() ? $customerGroupIds->random() : null,
                'promotion_group_id' => $promotionGroupIds->isNotEmpty() && fake()->boolean(50) ? $promotionGroupIds->random() : null,
                'assigned_to' => $ownerStaffIds->isNotEmpty() ? $ownerStaffIds->random() : null,
                'next_follow_up_at' => fake()->optional(0.65)->dateTimeBetween('now', '+21 days')?->format('Y-m-d'),
                'status' => fake()->randomElement($customerStatuses),
                'created_by' => $adminId,
                'updated_by' => $adminId,
            ]);
        });
    }

    protected function seedPatients(
        int $adminId,
        Collection $customerGroupIds,
        Collection $promotionGroupIds,
        Collection $doctorIds,
        Collection $ownerStaffIds,
    ): void {
        $occupations = ['Nhân viên văn phòng', 'Kinh doanh tự do', 'Giáo viên', 'Kỹ sư', 'Sinh viên', 'Bác sĩ', 'Điều dưỡng', 'Tài xế'];
        $firstVisitReasons = ['Đau răng kéo dài', 'Tư vấn trồng Implant', 'Niềng răng thẩm mỹ', 'Khám tổng quát định kỳ', 'Tẩy trắng răng', 'Nhổ răng khôn'];

        Patient::factory()->count(14)->create()->each(function (Patient $patient) use ($adminId, $customerGroupIds, $promotionGroupIds, $doctorIds, $ownerStaffIds, $occupations, $firstVisitReasons): void {
            $linkedCustomer = $patient->customer;
            $customerGroupId = $linkedCustomer?->customer_group_id ?? ($customerGroupIds->isNotEmpty() ? $customerGroupIds->random() : null);
            $promotionGroupId = $linkedCustomer?->promotion_group_id ?? ($promotionGroupIds->isNotEmpty() && fake()->boolean(40) ? $promotionGroupIds->random() : null);

            $patient->update([
                'birthday' => $patient->birthday ?? fake()->dateTimeBetween('-70 years', '-6 years')?->format('Y-m-d'),
                'cccd' => fake()->numerify('############'),
                'phone_secondary' => fake()->optional(0.4)->numerify('09########'),
                'occupation' => fake()->randomElement($occupations),
                'customer_group_id' => $customerGroupId,
                'promotion_group_id' => $promotionGroupId,
                'primary_doctor_id' => $doctorIds->isNotEmpty() ? $doctorIds->random() : null,
                'owner_staff_id' => $ownerStaffIds->isNotEmpty() ? $ownerStaffIds->random() : null,
                'first_visit_reason' => fake()->randomElement($firstVisitReasons),
                'note' => fake()->optional(0.65)->sentence(),
                'status' => fake()->randomElement(['active', 'active', 'active', 'inactive']),
                'created_by' => $adminId,
                'updated_by' => $adminId,
            ]);

            if ($linkedCustomer) {
                $linkedCustomer->update([
                    'status' => 'converted',
                    'customer_group_id' => $customerGroupId,
                    'promotion_group_id' => $promotionGroupId,
                    'assigned_to' => $patient->owner_staff_id,
                    'updated_by' => $adminId,
                ]);
            }
        });
    }

    protected function seedTreatmentJourney(Collection $branches): void
    {
        Appointment::factory()->count(15)->create();

        $availableServices = Service::query()->where('active', true)->get(['id', 'name', 'default_price']);
        if ($availableServices->isEmpty()) {
            return;
        }

        Patient::query()->inRandomOrder()->take(6)->get()->each(function (Patient $patient) use ($branches, $availableServices): void {
            $plan = TreatmentPlan::factory()->create([
                'patient_id' => $patient->id,
                'doctor_id' => User::role('Doctor')->inRandomOrder()->value('id'),
                'branch_id' => $patient->first_branch_id ?? $branches->first()->id,
                'status' => 'draft',
                'total_estimated_cost' => 0,
            ]);

            $selectedServices = $availableServices->shuffle()->take(random_int(2, min(4, $availableServices->count())));
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

                TreatmentSession::factory()->create([
                    'treatment_plan_id' => $plan->id,
                    'plan_item_id' => $item->id,
                    'status' => 'scheduled',
                ]);
            }

            $plan->update(['total_estimated_cost' => $estimatedTotal]);
        });

        Note::factory()->count(20)->create();
        BranchLog::factory()->count(5)->create();
    }
}
