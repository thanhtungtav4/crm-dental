<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CheckPatientCustomerDuplicates extends Command
{
    protected $signature = 'patients:check-customer-duplicates
        {--fix : Tự động giữ hồ sơ cũ nhất và gỡ customer_id khỏi các hồ sơ trùng}
        {--show-phone : Hiển thị thêm trùng số điện thoại theo chi nhánh}';

    protected $description = 'Kiểm tra (và tùy chọn sửa) duplicate customer_id trong bảng patients trước khi enforce unique.';

    public function handle(): int
    {
        $duplicates = $this->duplicateCustomerRows();

        if ($duplicates->isEmpty()) {
            $this->info('Khong tim thay duplicate theo customer_id.');
        } else {
            $this->warn('Tim thay duplicate theo customer_id:');
            $this->table(
                ['customer_id', 'total_patients', 'patient_ids'],
                $duplicates->map(fn (object $row) => [
                    $row->customer_id,
                    $row->total_patients,
                    $row->patient_ids,
                ])->all()
            );
        }

        if ($this->option('show-phone')) {
            $this->line('');
            $this->line('Kiem tra duplicate phone + first_branch_id:');
            $this->renderPhoneDuplicates();
        }

        if ($this->option('fix') && $duplicates->isNotEmpty()) {
            $this->line('');
            $this->warn('Bat dau sua duplicate customer_id...');
            $this->fixDuplicates($duplicates);
        }

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, object>
     */
    protected function duplicateCustomerRows(): Collection
    {
        return DB::table('patients')
            ->selectRaw('customer_id, COUNT(*) as total_patients, GROUP_CONCAT(id ORDER BY created_at ASC, id ASC) as patient_ids')
            ->whereNotNull('customer_id')
            ->groupBy('customer_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();
    }

    protected function fixDuplicates(Collection $duplicates): void
    {
        $affectedRows = 0;

        DB::transaction(function () use ($duplicates, &$affectedRows): void {
            foreach ($duplicates as $row) {
                $patientIds = collect(explode(',', (string) $row->patient_ids))
                    ->map(fn (string $id) => (int) $id)
                    ->filter();

                if ($patientIds->count() <= 1) {
                    continue;
                }

                $patientIds->shift(); // Keep oldest record.
                $affectedRows += DB::table('patients')
                    ->whereIn('id', $patientIds->all())
                    ->update([
                        'customer_id' => null,
                        'updated_at' => now(),
                    ]);
            }
        });

        $this->info("Da cap nhat {$affectedRows} dong duplicate.");
        $remaining = $this->duplicateCustomerRows()->count();
        $this->line("Duplicate con lai: {$remaining}");
    }

    protected function renderPhoneDuplicates(): void
    {
        $rows = DB::table('patients')
            ->selectRaw('first_branch_id, phone, COUNT(*) as total_patients, GROUP_CONCAT(id ORDER BY created_at ASC, id ASC) as patient_ids')
            ->whereNotNull('phone')
            ->whereRaw("TRIM(phone) <> ''")
            ->groupBy('first_branch_id', 'phone')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($rows->isEmpty()) {
            $this->info('Khong co duplicate phone + chi nhanh.');
            return;
        }

        $this->table(
            ['first_branch_id', 'phone', 'total_patients', 'patient_ids'],
            $rows->map(fn (object $row) => [
                $row->first_branch_id,
                $row->phone,
                $row->total_patients,
                $row->patient_ids,
            ])->all()
        );
    }
}

