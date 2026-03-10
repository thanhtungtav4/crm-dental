<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\ClinicalMediaAsset;
use App\Models\ClinicalMediaVersion;
use App\Models\Consent;
use App\Models\Customer;
use App\Models\Patient;
use App\Models\PlanItem;
use App\Models\Service;
use App\Models\TreatmentPlan;
use App\Models\User;
use Illuminate\Database\Seeder;

class ClinicalScenarioSeeder extends Seeder
{
    public const PLAN_ITEM_NAME = 'QA Clinical Consent Step';

    public const CONSENT_VERSION = 'v1-seed';

    public const MEDIA_STORAGE_PATH = 'clinical-media/qa/missing-checksum.jpg';

    public function run(): void
    {
        $branchId = Branch::query()->where('code', 'HCM-Q1')->value('id');
        $userIdsByEmail = User::query()
            ->whereIn('email', [
                'admin@demo.ident.test',
                'doctor.q1@demo.ident.test',
                'cskh.q1@demo.ident.test',
            ])
            ->pluck('id', 'email');

        if (! is_numeric($branchId) || ! is_numeric($userIdsByEmail->get('admin@demo.ident.test'))) {
            return;
        }

        $adminId = (int) $userIdsByEmail->get('admin@demo.ident.test');
        $doctorId = is_numeric($userIdsByEmail->get('doctor.q1@demo.ident.test'))
            ? (int) $userIdsByEmail->get('doctor.q1@demo.ident.test')
            : null;
        $frontDeskId = is_numeric($userIdsByEmail->get('cskh.q1@demo.ident.test'))
            ? (int) $userIdsByEmail->get('cskh.q1@demo.ident.test')
            : null;

        $customer = Customer::query()
            ->where('branch_id', (int) $branchId)
            ->where('source_detail', 'seed:clinical-scenario:consent')
            ->first() ?? new Customer;

        $customer->fill([
            'branch_id' => (int) $branchId,
            'full_name' => 'QA Clinical Consent',
            'phone' => '0909006091',
            'phone_search_hash' => Customer::phoneSearchHash('0909006091'),
            'email' => 'qa.clinical.consent@demo.ident.test',
            'email_search_hash' => Customer::emailSearchHash('qa.clinical.consent@demo.ident.test'),
            'source' => 'qa_seed',
            'source_detail' => 'seed:clinical-scenario:consent',
            'status' => 'converted',
            'assigned_to' => $frontDeskId,
            'created_by' => $adminId,
            'updated_by' => $adminId,
        ]);
        $customer->save();

        $patient = Patient::query()->firstOrNew([
            'patient_code' => 'PAT-QA-CLIN-001',
        ]);
        $patient->fill([
            'customer_id' => $customer->id,
            'patient_code' => 'PAT-QA-CLIN-001',
            'first_branch_id' => (int) $branchId,
            'full_name' => 'QA Clinical Consent',
            'phone' => '0909006091',
            'phone_search_hash' => Patient::phoneSearchHash('0909006091'),
            'email' => 'qa.clinical.consent@demo.ident.test',
            'email_search_hash' => Patient::emailSearchHash('qa.clinical.consent@demo.ident.test'),
            'gender' => 'female',
            'address' => 'Seed clinical scenario',
            'primary_doctor_id' => $doctorId,
            'owner_staff_id' => $frontDeskId,
            'status' => 'active',
            'created_by' => $adminId,
            'updated_by' => $adminId,
        ]);
        $patient->save();

        $service = Service::query()->updateOrCreate(
            ['name' => 'QA Clinical High Risk Service'],
            [
                'default_price' => 12_000_000,
                'requires_consent' => true,
                'active' => true,
            ],
        );

        $plan = TreatmentPlan::query()->updateOrCreate(
            ['title' => 'QA Clinical Consent Plan'],
            [
                'patient_id' => $patient->id,
                'doctor_id' => $doctorId,
                'branch_id' => $patient->first_branch_id,
                'status' => TreatmentPlan::STATUS_APPROVED,
                'created_by' => $adminId,
                'updated_by' => $adminId,
            ],
        );

        $planItem = PlanItem::query()->updateOrCreate(
            [
                'treatment_plan_id' => $plan->id,
                'name' => self::PLAN_ITEM_NAME,
            ],
            [
                'service_id' => $service->id,
                'quantity' => 1,
                'price' => 12_000_000,
                'estimated_cost' => 12_000_000,
                'actual_cost' => 0,
                'required_visits' => 2,
                'completed_visits' => 0,
                'status' => PlanItem::STATUS_PENDING,
                'approval_status' => PlanItem::APPROVAL_APPROVED,
                'patient_approved' => true,
            ],
        );

        $consent = Consent::query()->updateOrCreate(
            [
                'plan_item_id' => $planItem->id,
                'consent_version' => self::CONSENT_VERSION,
            ],
            [
                'patient_id' => $patient->id,
                'branch_id' => $patient->first_branch_id,
                'service_id' => $service->id,
                'consent_type' => 'high_risk',
                'status' => Consent::STATUS_PENDING,
                'signed_by' => null,
                'signed_at' => null,
                'note' => 'Seeded pending consent for clinical scenario.',
                'signature_context' => null,
            ],
        );

        $asset = ClinicalMediaAsset::query()->updateOrCreate(
            ['storage_path' => self::MEDIA_STORAGE_PATH],
            [
                'patient_id' => $patient->id,
                'branch_id' => $patient->first_branch_id,
                'consent_id' => $consent->id,
                'captured_by' => $doctorId,
                'captured_at' => now()->subDay(),
                'modality' => ClinicalMediaAsset::MODALITY_PHOTO,
                'phase' => 'pre',
                'mime_type' => 'image/jpeg',
                'file_size_bytes' => 1024,
                'checksum_sha256' => null,
                'storage_disk' => 'public',
                'status' => ClinicalMediaAsset::STATUS_ACTIVE,
                'retention_class' => ClinicalMediaAsset::RETENTION_CLINICAL_OPERATIONAL,
                'legal_hold' => false,
            ],
        );

        ClinicalMediaVersion::query()->updateOrCreate(
            [
                'clinical_media_asset_id' => $asset->id,
                'version_number' => 1,
            ],
            [
                'is_original' => true,
                'mime_type' => 'image/jpeg',
                'file_size_bytes' => 1024,
                'checksum_sha256' => null,
                'storage_disk' => 'public',
                'storage_path' => self::MEDIA_STORAGE_PATH,
                'created_by' => $doctorId,
            ],
        );
    }
}
