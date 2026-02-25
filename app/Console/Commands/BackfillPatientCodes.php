<?php

namespace App\Console\Commands;

use App\Models\Patient;
use App\Support\PatientCodeGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillPatientCodes extends Command
{
    protected $signature = 'patients:backfill-codes {--apply : Thực hiện cập nhật mã (mặc định chỉ preview)}';

    protected $description = 'Rà soát mã hồ sơ không theo chuẩn PAT-YYYYMMDD-XXXXXX và backfill nếu cần.';

    public function handle(): int
    {
        $invalidPatients = Patient::withTrashed()
            ->select('id', 'patient_code', 'created_at')
            ->get()
            ->filter(fn (Patient $patient) => ! PatientCodeGenerator::isStandard($patient->patient_code))
            ->values();

        if ($invalidPatients->isEmpty()) {
            $this->info('Khong co ma ho so can backfill.');
            return self::SUCCESS;
        }

        $this->warn("Tim thay {$invalidPatients->count()} ho so co ma khong dung chuan.");
        $this->table(
            ['id', 'patient_code_hien_tai', 'ngay_tao'],
            $invalidPatients->take(30)->map(fn (Patient $patient) => [
                $patient->id,
                $patient->patient_code,
                optional($patient->created_at)->format('Y-m-d H:i:s'),
            ])->all()
        );

        if (! $this->option('apply')) {
            $this->line('Chay lai voi --apply de cap nhat du lieu.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($invalidPatients): void {
            foreach ($invalidPatients as $patient) {
                $newCode = PatientCodeGenerator::generate($patient->created_at);

                Patient::withTrashed()
                    ->where('id', $patient->id)
                    ->update([
                        'patient_code' => $newCode,
                        'updated_at' => now(),
                    ]);
            }
        });

        $this->info("Da backfill {$invalidPatients->count()} ma ho so.");

        return self::SUCCESS;
    }
}
