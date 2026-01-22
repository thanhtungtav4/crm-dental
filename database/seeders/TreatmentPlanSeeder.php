<?php

namespace Database\Seeders;

use App\Models\Patient;
use App\Models\Service;
use App\Models\TreatmentPlan;
use App\Models\User;
use Illuminate\Database\Seeder;

class TreatmentPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $patients = Patient::take(5)->get();
        $doctors = User::take(3)->get();
        
        if ($patients->isEmpty() || $doctors->isEmpty()) {
            $this->command->warn('⚠️  Cần có ít nhất 5 bệnh nhân và 3 users để seed dữ liệu treatment plans');
            return;
        }

        // Get services for different treatments
        $orthodonticService = Service::where('name', 'LIKE', '%niềng%')->orWhere('name', 'LIKE', '%chỉnh nha%')->first();
        $implantService = Service::where('name', 'LIKE', '%implant%')->orWhere('name', 'LIKE', '%cấy ghép%')->first();
        $whiteningService = Service::where('name', 'LIKE', '%tẩy trắng%')->orWhere('name', 'LIKE', '%trắng răng%')->first();
        $rootCanalService = Service::where('name', 'LIKE', '%nội nha%')->orWhere('name', 'LIKE', '%tủy%')->first();
        $crownService = Service::where('name', 'LIKE', '%răng sứ%')->orWhere('name', 'LIKE', '%crown%')->first();

        $this->command->info('🦷 Seeding treatment plans...');

        // Plan 1: Orthodontic Treatment (12 months, 24 visits) - In Progress
        $plan1 = TreatmentPlan::create([
            'patient_id' => $patients[0]->id,
            'doctor_id' => $doctors[0]->id,
            'title' => 'Niềng răng chỉnh nha toàn hàm',
            'notes' => 'Bệnh nhân có tình trạng răng khấp khểnh, cần niềng răng để chỉnh nha. Dự kiến 12 tháng.',
            'status' => 'in_progress',
            'priority' => 'high',
            'total_estimated_cost' => 45000000,
            'total_cost' => 23000000,
            'expected_start_date' => now()->subMonths(4),
            'expected_end_date' => now()->addMonths(8),
            'actual_start_date' => now()->subMonths(4),
            'total_visits' => 24,
            'completed_visits' => 8,
            'progress_percentage' => 33,
        ]);

        // Add plan items for orthodontic
        $plan1->planItems()->create([
            'service_id' => $orthodonticService?->id,
            'name' => 'Lắp mắc cài toàn hàm trên',
            'tooth_number' => '11-18,21-28',
            'tooth_notation' => 'fdi',
            'quantity' => 1,
            'estimated_cost' => 20000000,
            'actual_cost' => 20000000,
            'required_visits' => 1,
            'completed_visits' => 1,
            'progress_percentage' => 100,
            'status' => 'completed',
            'priority' => 'high',
            'started_at' => now()->subMonths(4),
            'completed_at' => now()->subMonths(4),
        ]);

        $plan1->planItems()->create([
            'service_id' => $orthodonticService?->id,
            'name' => 'Lắp mắc cài toàn hàm dưới',
            'tooth_number' => '31-38,41-48',
            'tooth_notation' => 'fdi',
            'quantity' => 1,
            'estimated_cost' => 20000000,
            'actual_cost' => 20000000,
            'required_visits' => 1,
            'completed_visits' => 1,
            'progress_percentage' => 100,
            'status' => 'completed',
            'priority' => 'high',
            'started_at' => now()->subMonths(3)->subWeeks(3),
            'completed_at' => now()->subMonths(3)->subWeeks(3),
        ]);

        $plan1->planItems()->create([
            'service_id' => $orthodonticService?->id,
            'name' => 'Tái khám và điều chỉnh dây cung',
            'quantity' => 1,
            'estimated_cost' => 500000,
            'actual_cost' => 0,
            'required_visits' => 22,
            'completed_visits' => 6,
            'progress_percentage' => 27,
            'status' => 'in_progress',
            'priority' => 'normal',
            'started_at' => now()->subMonths(3)->subWeeks(2),
            'notes' => 'Tái khám định kỳ mỗi 2-3 tuần',
        ]);

        $plan1->updateProgress();

        $this->command->info('✓ Created orthodontic treatment plan (12 months, 33% completed)');

        // Plan 2: Dental Implant (3 months, 4 visits) - Pending
        $plan2 = TreatmentPlan::create([
            'patient_id' => $patients[1]->id,
            'doctor_id' => $doctors[1]->id,
            'title' => 'Cấy ghép Implant răng số 16',
            'notes' => 'Bệnh nhân đã mất răng số 16, cần cấy ghép implant để phục hồi chức năng nhai.',
            'status' => 'approved',
            'priority' => 'normal',
            'total_estimated_cost' => 18000000,
            'expected_start_date' => now()->addDays(7),
            'expected_end_date' => now()->addMonths(3),
            'total_visits' => 4,
            'completed_visits' => 0,
            'progress_percentage' => 0,
        ]);

        $plan2->planItems()->create([
            'service_id' => $implantService?->id,
            'name' => 'Phẫu thuật cấy implant răng 16',
            'tooth_number' => '16',
            'tooth_notation' => 'fdi',
            'quantity' => 1,
            'estimated_cost' => 12000000,
            'required_visits' => 1,
            'completed_visits' => 0,
            'progress_percentage' => 0,
            'status' => 'pending',
            'priority' => 'normal',
        ]);

        $plan2->planItems()->create([
            'service_id' => $implantService?->id,
            'name' => 'Lắp trụ abutment',
            'tooth_number' => '16',
            'tooth_notation' => 'fdi',
            'quantity' => 1,
            'estimated_cost' => 3000000,
            'required_visits' => 1,
            'completed_visits' => 0,
            'progress_percentage' => 0,
            'status' => 'pending',
            'priority' => 'normal',
            'notes' => 'Thực hiện sau 8 tuần cấy implant',
        ]);

        $plan2->planItems()->create([
            'service_id' => $crownService?->id,
            'name' => 'Lắp mão răng sứ',
            'tooth_number' => '16',
            'tooth_notation' => 'fdi',
            'quantity' => 1,
            'estimated_cost' => 3000000,
            'required_visits' => 2,
            'completed_visits' => 0,
            'progress_percentage' => 0,
            'status' => 'pending',
            'priority' => 'normal',
            'notes' => 'Lấy dấu và lắp mão (2 lần)',
        ]);

        $plan2->updateProgress();

        $this->command->info('✓ Created implant treatment plan (3 months, approved)');

        // Plan 3: Teeth Whitening (1 month, 2 visits) - Draft
        $plan3 = TreatmentPlan::create([
            'patient_id' => $patients[2]->id,
            'doctor_id' => $doctors[0]->id,
            'title' => 'Tẩy trắng răng toàn hàm',
            'notes' => 'Bệnh nhân muốn cải thiện màu sắc răng, hiện tại răng bị vàng do uống trà, cafe.',
            'status' => 'draft',
            'priority' => 'low',
            'total_estimated_cost' => 3500000,
            'expected_start_date' => now()->addDays(14),
            'expected_end_date' => now()->addMonths(1),
            'total_visits' => 2,
            'completed_visits' => 0,
            'progress_percentage' => 0,
        ]);

        $plan3->planItems()->create([
            'service_id' => $whiteningService?->id,
            'name' => 'Tẩy trắng răng toàn hàm (lần 1)',
            'tooth_number' => '11-18,21-28,31-38,41-48',
            'tooth_notation' => 'fdi',
            'quantity' => 1,
            'estimated_cost' => 2000000,
            'required_visits' => 1,
            'completed_visits' => 0,
            'progress_percentage' => 0,
            'status' => 'pending',
            'priority' => 'low',
        ]);

        $plan3->planItems()->create([
            'service_id' => $whiteningService?->id,
            'name' => 'Tẩy trắng răng toàn hàm (lần 2)',
            'tooth_number' => '11-18,21-28,31-38,41-48',
            'tooth_notation' => 'fdi',
            'quantity' => 1,
            'estimated_cost' => 1500000,
            'required_visits' => 1,
            'completed_visits' => 0,
            'progress_percentage' => 0,
            'status' => 'pending',
            'priority' => 'low',
            'notes' => 'Thực hiện sau 1 tuần lần 1',
        ]);

        $plan3->updateProgress();

        $this->command->info('✓ Created whitening treatment plan (1 month, draft)');

        // Plan 4: Root Canal Treatment (2 weeks, 3 visits) - Completed
        $plan4 = TreatmentPlan::create([
            'patient_id' => $patients[3]->id,
            'doctor_id' => $doctors[2]->id,
            'title' => 'Điều trị tủy răng số 26',
            'notes' => 'Bệnh nhân đau răng số 26, chụp phim thấy tủy bị viêm. Cần điều trị nội nha.',
            'status' => 'completed',
            'priority' => 'urgent',
            'total_estimated_cost' => 2500000,
            'total_cost' => 2800000,
            'expected_start_date' => now()->subWeeks(4),
            'expected_end_date' => now()->subWeeks(2),
            'actual_start_date' => now()->subWeeks(4),
            'actual_end_date' => now()->subWeeks(1),
            'total_visits' => 3,
            'completed_visits' => 3,
            'progress_percentage' => 100,
        ]);

        $plan4->planItems()->create([
            'service_id' => $rootCanalService?->id,
            'name' => 'Mở tủy và làm sạch ống tủy (lần 1)',
            'tooth_number' => '26',
            'tooth_notation' => 'fdi',
            'quantity' => 1,
            'estimated_cost' => 800000,
            'actual_cost' => 800000,
            'required_visits' => 1,
            'completed_visits' => 1,
            'progress_percentage' => 100,
            'status' => 'completed',
            'priority' => 'urgent',
            'started_at' => now()->subWeeks(4),
            'completed_at' => now()->subWeeks(4),
        ]);

        $plan4->planItems()->create([
            'service_id' => $rootCanalService?->id,
            'name' => 'Làm sạch và đặt thuốc (lần 2)',
            'tooth_number' => '26',
            'tooth_notation' => 'fdi',
            'quantity' => 1,
            'estimated_cost' => 700000,
            'actual_cost' => 900000,
            'required_visits' => 1,
            'completed_visits' => 1,
            'progress_percentage' => 100,
            'status' => 'completed',
            'priority' => 'urgent',
            'started_at' => now()->subWeeks(3),
            'completed_at' => now()->subWeeks(3),
            'notes' => 'Chi phí tăng do cần thêm thuốc đặc biệt',
        ]);

        $plan4->planItems()->create([
            'service_id' => $rootCanalService?->id,
            'name' => 'Trám bít ống tủy và phục hồi',
            'tooth_number' => '26',
            'tooth_notation' => 'fdi',
            'quantity' => 1,
            'estimated_cost' => 1000000,
            'actual_cost' => 1100000,
            'required_visits' => 1,
            'completed_visits' => 1,
            'progress_percentage' => 100,
            'status' => 'completed',
            'priority' => 'urgent',
            'started_at' => now()->subWeeks(1),
            'completed_at' => now()->subWeeks(1),
        ]);

        $plan4->updateProgress();

        $this->command->info('✓ Created root canal treatment plan (completed, cost variance +12%)');

        // Plan 5: Full Mouth Reconstruction (6 months, 15 visits) - In Progress
        $plan5 = TreatmentPlan::create([
            'patient_id' => $patients[4]->id,
            'doctor_id' => $doctors[1]->id,
            'title' => 'Phục hồi toàn hàm răng',
            'notes' => 'Bệnh nhân có nhiều răng hỏng, cần phục hồi toàn diện cả 2 hàm. Kế hoạch dài hạn 6 tháng.',
            'status' => 'in_progress',
            'priority' => 'high',
            'total_estimated_cost' => 85000000,
            'total_cost' => 12000000,
            'expected_start_date' => now()->subMonths(2),
            'expected_end_date' => now()->addMonths(4),
            'actual_start_date' => now()->subMonths(2),
            'total_visits' => 15,
            'completed_visits' => 2,
            'progress_percentage' => 13,
        ]);

        $plan5->planItems()->create([
            'service_id' => $rootCanalService?->id,
            'name' => 'Điều trị nội nha răng 11, 21',
            'tooth_number' => '11,21',
            'tooth_notation' => 'fdi',
            'quantity' => 2,
            'estimated_cost' => 5000000,
            'actual_cost' => 5200000,
            'required_visits' => 6,
            'completed_visits' => 6,
            'progress_percentage' => 100,
            'status' => 'completed',
            'priority' => 'high',
            'started_at' => now()->subMonths(2),
            'completed_at' => now()->subMonths(1),
        ]);

        $plan5->planItems()->create([
            'service_id' => $crownService?->id,
            'name' => 'Làm răng sứ cho 8 răng cửa (11-14, 21-24)',
            'tooth_number' => '11-14,21-24',
            'tooth_notation' => 'fdi',
            'quantity' => 8,
            'estimated_cost' => 40000000,
            'required_visits' => 4,
            'completed_visits' => 0,
            'progress_percentage' => 0,
            'status' => 'pending',
            'priority' => 'high',
            'notes' => 'Chờ hoàn thành điều trị tủy',
        ]);

        $plan5->planItems()->create([
            'service_id' => $implantService?->id,
            'name' => 'Cấy implant răng 16, 26, 36, 46',
            'tooth_number' => '16,26,36,46',
            'tooth_notation' => 'fdi',
            'quantity' => 4,
            'estimated_cost' => 40000000,
            'required_visits' => 5,
            'completed_visits' => 0,
            'progress_percentage' => 0,
            'status' => 'pending',
            'priority' => 'normal',
            'notes' => 'Cấy ghép 4 răng hàm',
        ]);

        $plan5->updateProgress();

        $this->command->info('✓ Created full mouth reconstruction plan (6 months, 13% completed)');

        // Additional plans with different scenarios
        $this->createCancelledPlan($patients[0], $doctors[0]);
        $this->createOverduePlan($patients[1], $doctors[2]);

        $this->command->info('🎉 Successfully seeded 7 treatment plans with realistic data!');
    }

    private function createCancelledPlan($patient, $doctor)
    {
        $plan = TreatmentPlan::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'title' => 'Cấy ghép xương và implant',
            'notes' => 'Bệnh nhân hủy kế hoạch do chưa sẵn sàng về tài chính.',
            'status' => 'cancelled',
            'priority' => 'normal',
            'total_estimated_cost' => 35000000,
            'expected_start_date' => now()->subWeeks(2),
            'expected_end_date' => now()->addMonths(4),
            'total_visits' => 6,
            'completed_visits' => 0,
            'progress_percentage' => 0,
        ]);

        $plan->planItems()->create([
            'name' => 'Cấy ghép xương hàm trên',
            'tooth_number' => '16',
            'tooth_notation' => 'fdi',
            'quantity' => 1,
            'estimated_cost' => 20000000,
            'required_visits' => 3,
            'completed_visits' => 0,
            'progress_percentage' => 0,
            'status' => 'cancelled',
            'priority' => 'normal',
        ]);

        $this->command->info('✓ Created cancelled treatment plan');
    }

    private function createOverduePlan($patient, $doctor)
    {
        $plan = TreatmentPlan::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'title' => 'Điều trị viêm nha chu',
            'notes' => 'Kế hoạch đã quá hạn, bệnh nhân không đến tái khám đúng hẹn.',
            'status' => 'in_progress',
            'priority' => 'high',
            'total_estimated_cost' => 8000000,
            'total_cost' => 3000000,
            'expected_start_date' => now()->subMonths(3),
            'expected_end_date' => now()->subWeeks(2),
            'actual_start_date' => now()->subMonths(3),
            'total_visits' => 6,
            'completed_visits' => 2,
            'progress_percentage' => 33,
        ]);

        $plan->planItems()->create([
            'name' => 'Cạo vôi răng và lấy cao răng',
            'tooth_number' => '11-18,21-28,31-38,41-48',
            'tooth_notation' => 'fdi',
            'quantity' => 1,
            'estimated_cost' => 2000000,
            'actual_cost' => 2000000,
            'required_visits' => 2,
            'completed_visits' => 2,
            'progress_percentage' => 100,
            'status' => 'completed',
            'priority' => 'high',
            'started_at' => now()->subMonths(3),
            'completed_at' => now()->subMonths(2)->subWeeks(2),
        ]);

        $plan->planItems()->create([
            'name' => 'Điều trị viêm nha chu sâu',
            'tooth_number' => '16,26,36,46',
            'tooth_notation' => 'fdi',
            'quantity' => 4,
            'estimated_cost' => 6000000,
            'actual_cost' => 1000000,
            'required_visits' => 4,
            'completed_visits' => 1,
            'progress_percentage' => 25,
            'status' => 'in_progress',
            'priority' => 'high',
            'started_at' => now()->subMonths(2),
            'notes' => 'Bệnh nhân không đến tái khám',
        ]);

        $plan->updateProgress();

        $this->command->info('✓ Created overdue treatment plan (needs follow-up)');
    }
}
