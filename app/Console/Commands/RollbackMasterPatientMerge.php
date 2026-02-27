<?php

namespace App\Console\Commands;

use App\Services\MasterPatientMergeService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class RollbackMasterPatientMerge extends Command
{
    protected $signature = 'mpi:merge-rollback
        {merge_id : Merge ID cần rollback}
        {--note= : Ghi chú rollback}';

    protected $description = 'Rollback merge MPI đã apply, khôi phục mapping bệnh nhân cũ.';

    public function __construct(protected MasterPatientMergeService $mergeService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $mergeId = (int) $this->argument('merge_id');
        $note = $this->option('note') ? (string) $this->option('note') : null;

        try {
            $merge = $this->mergeService->rollback(
                mergeId: $mergeId,
                note: $note,
                actorId: auth()->id(),
            );
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ((array) $messages as $message) {
                    $this->error((string) $message);
                }
            }

            return self::INVALID;
        }

        $this->table(
            ['Merge ID', 'Canonical', 'Merged', 'Status', 'Rolled Back At'],
            [[
                $merge->id,
                $merge->canonical_patient_id,
                $merge->merged_patient_id,
                $merge->status,
                optional($merge->rolled_back_at)->toDateTimeString(),
            ]]
        );

        $this->info('MPI merge rollback completed.');

        return self::SUCCESS;
    }
}
