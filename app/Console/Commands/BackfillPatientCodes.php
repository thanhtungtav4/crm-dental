<?php

namespace App\Console\Commands;

use App\Models\Patient;
use App\Support\PatientCodeGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BackfillPatientCodes extends Command
{
    protected $signature = 'patients:backfill-codes {--apply : Thực hiện cập nhật mã (mặc định chỉ preview)}';

    protected $description = 'Rà soát mã hồ sơ không theo chuẩn PAT-YYYYMMDD-XXXXXX và backfill nếu cần.';

    public function handle(): int
    {
        $invalidPatientIds = [];
        $previewRows = [];
        $invalidCount = 0;

        Patient::withTrashed()
            ->select('id', 'patient_code', 'created_at')
            ->lazyById()
            ->each(function (Patient $patient) use (&$invalidPatientIds, &$previewRows, &$invalidCount): void {
                if (PatientCodeGenerator::isStandard($patient->patient_code)) {
                    return;
                }

                $invalidCount++;
                $invalidPatientIds[] = $patient->id;

                if (count($previewRows) < 30) {
                    $previewRows[] = [
                        $patient->id,
                        $patient->patient_code,
                        optional($patient->created_at)->format('Y-m-d H:i:s'),
                    ];
                }
            });

        if ($invalidCount === 0) {
            $this->info('Khong co ma ho so can backfill.');

            return self::SUCCESS;
        }

        $this->warn("Tim thay {$invalidCount} ho so co ma khong dung chuan.");
        $this->table(['id', 'patient_code_hien_tai', 'ngay_tao'], $previewRows);

        if (! $this->option('apply')) {
            $this->line('Chay lai voi --apply de cap nhat du lieu.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($invalidPatientIds): void {
            Collection::make($invalidPatientIds)
                ->chunk(500)
                ->each(function (Collection $patientIds): void {
                    Patient::withTrashed()
                        ->whereKey($patientIds->all())
                        ->get(['id', 'created_at'])
                        ->each(function (Patient $patient): void {
                            Patient::withTrashed()
                                ->whereKey($patient->id)
                                ->update([
                                    'patient_code' => PatientCodeGenerator::generate($patient->created_at),
                                    'updated_at' => now(),
                                ]);
                        });
                });
        });

        $this->info("Da backfill {$invalidCount} ma ho so.");

        return self::SUCCESS;
    }
}
