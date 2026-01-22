<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\PlanItem;
use App\Models\Service;
use App\Models\TreatmentPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TreatmentPlanProgressTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected User $doctor;
    protected Patient $patient;
    protected Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test data
        $this->doctor = User::factory()->create(['name' => 'Dr. Test']);
        $this->patient = Patient::factory()->create(['full_name' => 'Test Patient']);
        $this->service = Service::factory()->create(['name' => 'Test Service', 'price' => 1000000]);
    }

    /** @test */
    public function it_can_create_treatment_plan_with_basic_info()
    {
        $plan = TreatmentPlan::create([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'title' => 'Test Treatment Plan',
            'status' => 'draft',
            'priority' => 'normal',
            'total_estimated_cost' => 5000000,
            'expected_start_date' => now(),
            'expected_end_date' => now()->addMonths(3),
        ]);

        $this->assertDatabaseHas('treatment_plans', [
            'title' => 'Test Treatment Plan',
            'status' => 'draft',
            'priority' => 'normal',
        ]);

        $this->assertEquals(0, $plan->progress_percentage);
        $this->assertEquals(0, $plan->completed_visits);
        $this->assertEquals(0, $plan->total_visits);
    }

    /** @test */
    public function it_can_add_plan_items_with_tooth_notation()
    {
        $plan = TreatmentPlan::factory()->create([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
        ]);

        // Single tooth
        $item1 = $plan->planItems()->create([
            'service_id' => $this->service->id,
            'name' => 'Crown for tooth 16',
            'tooth_number' => '16',
            'tooth_notation' => 'fdi',
            'quantity' => 1,
            'estimated_cost' => 3000000,
            'required_visits' => 2,
        ]);

        $this->assertEquals('🦷 16 (FDI)', $item1->getToothNotationDisplay());

        // Range of teeth
        $item2 = $plan->planItems()->create([
            'service_id' => $this->service->id,
            'name' => 'Braces for upper front',
            'tooth_number' => '11-14',
            'tooth_notation' => 'fdi',
            'quantity' => 1,
            'estimated_cost' => 10000000,
            'required_visits' => 12,
        ]);

        $this->assertEquals([11, 12, 13, 14], $item2->getToothNumbers());

        // Multiple teeth (comma-separated)
        $item3 = $plan->planItems()->create([
            'service_id' => $this->service->id,
            'name' => 'Filling for molars',
            'tooth_number' => '16,26,36,46',
            'tooth_notation' => 'fdi',
            'quantity' => 4,
            'estimated_cost' => 4000000,
            'required_visits' => 4,
        ]);

        $this->assertEquals([16, 26, 36, 46], $item3->getToothNumbers());
    }

    /** @test */
    public function it_calculates_progress_from_plan_items()
    {
        $plan = TreatmentPlan::factory()->create([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
        ]);

        // Add 3 items
        $item1 = $plan->planItems()->create([
            'name' => 'Item 1',
            'required_visits' => 2,
            'completed_visits' => 2,
            'progress_percentage' => 100,
        ]);

        $item2 = $plan->planItems()->create([
            'name' => 'Item 2',
            'required_visits' => 4,
            'completed_visits' => 2,
            'progress_percentage' => 50,
        ]);

        $item3 = $plan->planItems()->create([
            'name' => 'Item 3',
            'required_visits' => 2,
            'completed_visits' => 0,
            'progress_percentage' => 0,
        ]);

        $plan->updateProgress();

        // Average: (100 + 50 + 0) / 3 = 50
        $this->assertEquals(50, $plan->progress_percentage);
        $this->assertEquals(4, $plan->completed_visits); // 2 + 2 + 0
        $this->assertEquals(8, $plan->total_visits); // 2 + 4 + 2
    }

    /** @test */
    public function it_auto_updates_status_based_on_progress()
    {
        $plan = TreatmentPlan::factory()->create([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'status' => 'approved',
        ]);

        $item = $plan->planItems()->create([
            'name' => 'Test Item',
            'required_visits' => 2,
            'completed_visits' => 0,
            'progress_percentage' => 0,
            'status' => 'pending',
        ]);

        // Start progress
        $item->update(['completed_visits' => 1, 'progress_percentage' => 50, 'status' => 'in_progress']);
        $item->updateProgress();

        $plan->refresh();
        $this->assertEquals('in_progress', $plan->status);

        // Complete
        $item->update(['completed_visits' => 2, 'progress_percentage' => 100, 'status' => 'completed']);
        $item->updateProgress();

        $plan->refresh();
        $this->assertEquals('completed', $plan->status);
        $this->assertNotNull($plan->actual_end_date);
    }

    /** @test */
    public function it_increments_visits_with_complete_visit_method()
    {
        $plan = TreatmentPlan::factory()->create([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
        ]);

        $item = $plan->planItems()->create([
            'name' => 'Multi-visit treatment',
            'required_visits' => 5,
            'completed_visits' => 0,
            'progress_percentage' => 0,
        ]);

        // Complete first visit
        $item->completeVisit();
        $this->assertEquals(1, $item->completed_visits);
        $this->assertEquals(20, $item->progress_percentage); // 1/5 * 100

        // Complete second visit
        $item->completeVisit();
        $this->assertEquals(2, $item->completed_visits);
        $this->assertEquals(40, $item->progress_percentage); // 2/5 * 100

        // Try to complete beyond required (should cap)
        $item->update(['completed_visits' => 5]);
        $item->completeVisit();
        $this->assertEquals(5, $item->completed_visits); // Shouldn't exceed 5
    }

    /** @test */
    public function it_calculates_cost_variance()
    {
        $plan = TreatmentPlan::factory()->create([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'total_estimated_cost' => 10000000,
            'total_cost' => 12000000, // Over budget
        ]);

        $this->assertEquals(2000000, $plan->getCostVariance());
        $this->assertEquals(20.0, $plan->getCostVariancePercentage());

        $item = $plan->planItems()->create([
            'name' => 'Test Item',
            'estimated_cost' => 5000000,
            'actual_cost' => 4500000, // Under budget
        ]);

        $this->assertEquals(-500000, $item->getCostVariance());
    }

    /** @test */
    public function it_detects_overdue_plans()
    {
        // Plan that's overdue
        $overduePlan = TreatmentPlan::factory()->create([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'status' => 'in_progress',
            'expected_end_date' => now()->subDays(7),
        ]);

        $this->assertTrue($overduePlan->isOverdue());

        // Plan that's on time
        $onTimePlan = TreatmentPlan::factory()->create([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'status' => 'in_progress',
            'expected_end_date' => now()->addDays(7),
        ]);

        $this->assertFalse($onTimePlan->isOverdue());

        // Completed plan (never overdue)
        $completedPlan = TreatmentPlan::factory()->create([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'status' => 'completed',
            'expected_end_date' => now()->subDays(7),
        ]);

        $this->assertFalse($completedPlan->isOverdue());
    }

    /** @test */
    public function it_provides_correct_status_labels()
    {
        $plan = TreatmentPlan::factory()->create([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'status' => 'in_progress',
            'priority' => 'urgent',
        ]);

        $this->assertEquals('Đang thực hiện', $plan->getStatusLabel());
        $this->assertEquals('Khẩn cấp', $plan->getPriorityLabel());
        
        $item = $plan->planItems()->create([
            'name' => 'Test',
            'status' => 'completed',
            'priority' => 'high',
        ]);

        $this->assertEquals('Hoàn thành', $item->getStatusLabel());
        $this->assertEquals('Cao', $item->getPriorityLabel());
    }

    /** @test */
    public function it_uses_scopes_correctly()
    {
        // Create plans with different statuses
        $inProgressPlan = TreatmentPlan::factory()->create([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'status' => 'in_progress',
        ]);

        $completedPlan = TreatmentPlan::factory()->create([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'status' => 'completed',
        ]);

        $overduePlan = TreatmentPlan::factory()->create([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'status' => 'in_progress',
            'expected_end_date' => now()->subDays(5),
        ]);

        // Test scopes
        $this->assertEquals(1, TreatmentPlan::inProgress()->count());
        $this->assertEquals(1, TreatmentPlan::completed()->count());
        $this->assertEquals(1, TreatmentPlan::overdue()->count());
        $this->assertEquals(3, TreatmentPlan::forPatient($this->patient->id)->count());
        $this->assertEquals(3, TreatmentPlan::byDoctor($this->doctor->id)->count());
    }

    /** @test */
    public function it_syncs_child_progress_to_parent_plan()
    {
        $plan = TreatmentPlan::factory()->create([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
        ]);

        $item1 = $plan->planItems()->create([
            'name' => 'Item 1',
            'required_visits' => 2,
            'completed_visits' => 0,
            'estimated_cost' => 2000000,
            'actual_cost' => 0,
        ]);

        $item2 = $plan->planItems()->create([
            'name' => 'Item 2',
            'required_visits' => 3,
            'completed_visits' => 0,
            'estimated_cost' => 3000000,
            'actual_cost' => 0,
        ]);

        // Initially 0%
        $plan->updateProgress();
        $this->assertEquals(0, $plan->progress_percentage);

        // Complete one item
        $item1->update([
            'completed_visits' => 2,
            'progress_percentage' => 100,
            'actual_cost' => 2100000,
        ]);
        $item1->updateProgress(); // This should sync to parent

        $plan->refresh();
        $this->assertEquals(50, $plan->progress_percentage); // (100 + 0) / 2
        $this->assertEquals(2, $plan->completed_visits);
        $this->assertEquals(5, $plan->total_visits);
        $this->assertEquals(2100000, $plan->total_cost);

        // Complete second item
        $item2->update([
            'completed_visits' => 3,
            'progress_percentage' => 100,
            'actual_cost' => 2900000,
        ]);
        $item2->updateProgress();

        $plan->refresh();
        $this->assertEquals(100, $plan->progress_percentage);
        $this->assertEquals(5, $plan->completed_visits);
        $this->assertEquals('completed', $plan->status);
        $this->assertEquals(5000000, $plan->total_cost); // 2100000 + 2900000
    }

    /** @test */
    public function it_handles_plan_item_deletion_and_updates_parent()
    {
        $plan = TreatmentPlan::factory()->create([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
        ]);

        $item1 = $plan->planItems()->create([
            'name' => 'Item 1',
            'required_visits' => 2,
            'completed_visits' => 2,
            'progress_percentage' => 100,
        ]);

        $item2 = $plan->planItems()->create([
            'name' => 'Item 2',
            'required_visits' => 2,
            'completed_visits' => 0,
            'progress_percentage' => 0,
        ]);

        $plan->updateProgress();
        $this->assertEquals(50, $plan->progress_percentage);

        // Delete item2
        $item2->delete();
        $plan->updateProgress();

        // Now only item1 remains (100%)
        $this->assertEquals(100, $plan->progress_percentage);
    }

    /** @test */
    public function it_calculates_progress_badge_colors()
    {
        $plan = TreatmentPlan::factory()->create([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'progress_percentage' => 0,
        ]);

        $this->assertEquals('gray', $plan->getProgressBadgeColor());

        $plan->update(['progress_percentage' => 25]);
        $this->assertEquals('warning', $plan->getProgressBadgeColor());

        $plan->update(['progress_percentage' => 75]);
        $this->assertEquals('info', $plan->getProgressBadgeColor());

        $plan->update(['progress_percentage' => 100]);
        $this->assertEquals('success', $plan->getProgressBadgeColor());
    }
}
