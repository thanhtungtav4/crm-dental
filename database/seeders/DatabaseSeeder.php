<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Billing;
use App\Models\Branch;
use App\Models\BranchLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Material;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Str;
use App\Models\Note;
use App\Models\Payment;
use App\Models\PlanItem;
use App\Models\TreatmentMaterial;
use App\Models\TreatmentPlan;
use App\Models\TreatmentSession;
use App\Models\Service;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

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
            ToothConditionSeeder::class,
        ]);

        // Branches
        $branches = Branch::factory()->count(2)->create();

        // Super Admin
        $admin = User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'branch_id' => $branches->first()->id,
        ]);
        $admin->assignRole('Admin');

        // Manager, Doctor, CSKH
        $roles = ['Manager', 'Doctor', 'CSKH'];
        foreach ($roles as $i => $roleName) {
            $user = User::factory()->create([
                'name' => $roleName . ' User',
                'email' => strtolower($roleName) . '@example.com',
                'password' => Hash::make('password'),
                'branch_id' => $branches[$i % $branches->count()]->id,
            ]);
            $user->assignRole($roleName);
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

        // Leads & Patients
        Customer::factory()->count(20)->create();
        Patient::factory()->count(10)->create();

        // Create additional doctors as Users with Doctor role if needed
        if (\App\Models\User::role('Doctor')->count() < 3) {
            for ($i = 0; $i < 3; $i++) {
                $doc = \App\Models\User::factory()->create([
                    'branch_id' => $branches[$i % $branches->count()]->id,
                ]);
                $doc->assignRole('Doctor');
            }
        }

        // Appointments
        Appointment::factory()->count(15)->create();

        // Services (VI)
        $services = collect([
            ['name' => 'Nhổ răng khôn', 'code' => 'NHO-RANG-KHON', 'unit' => 'lần', 'default_price' => 800000],
            ['name' => 'Trám răng', 'code' => 'TRAM-RANG', 'unit' => 'răng', 'default_price' => 500000],
            ['name' => 'Điều trị tủy', 'code' => 'DIEU-TRI-TUY', 'unit' => 'răng', 'default_price' => 1200000],
        ]);
        foreach ($services as $svc) {
            Service::updateOrCreate(['code' => $svc['code']], $svc);
        }

        // Treatment Plans (VI): Kế hoạch + hạng mục + buổi điều trị với vật tư dự kiến
        $patients = Patient::take(5)->get();
        foreach ($patients as $p) {
            $plan = TreatmentPlan::factory()->create([
                'patient_id' => $p->id,
                'doctor_id' => User::role('Doctor')->inRandomOrder()->value('id'),
                'branch_id' => $p->branch_id ?? $branches->first()->id,
                'status' => 'draft',
                'total_estimated_cost' => 0,
            ]);

            $planServices = [
                ['code' => 'NHO-RANG-KHON', 'supplies' => [['Composite' => 1], ['Thuốc tê' => 2], ['Kim tiêm' => 2]]],
                ['code' => 'TRAM-RANG', 'supplies' => [['Composite' => 2], ['Bông gạc' => 1]]],
                ['code' => 'DIEU-TRI-TUY', 'supplies' => [['Thuốc tê' => 1], ['Kim tiêm' => 1]]],
            ];

            $estimatedTotal = 0;
            foreach (array_rand($planServices, 2) as $idx) {
                $svcMeta = $planServices[$idx];
                $svc = Service::where('code', $svcMeta['code'])->first();
                $item = PlanItem::create([
                    'treatment_plan_id' => $plan->id,
                    'name' => $svc->name,
                    'service_id' => $svc->id,
                    'quantity' => 1,
                    'price' => $svc->default_price,
                    'estimated_supplies' => $svcMeta['supplies'],
                ]);
                $estimatedTotal += $svc->default_price;

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
