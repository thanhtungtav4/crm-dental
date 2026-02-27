<?php

namespace App\Console\Commands;

use App\Services\MasterPatientMergeService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class MergeMasterPatient extends Command
{
    protected $signature = 'mpi:merge
        {canonical_patient_id : Patient chính (golden record)}
        {merged_patient_id : Patient bị gộp}
        {--duplicate_case_id= : Case duplicate đang review}
        {--reason= : Lý do merge}
        {--meta=* : Metadata key=value bổ sung}';

    protected $description = 'Merge MPI patient duplicate về golden record và lưu history mapping.';

    public function __construct(protected MasterPatientMergeService $mergeService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $canonicalPatientId = (int) $this->argument('canonical_patient_id');
        $mergedPatientId = (int) $this->argument('merged_patient_id');
        $duplicateCaseId = $this->option('duplicate_case_id') !== null
            ? (int) $this->option('duplicate_case_id')
            : null;
        $reason = $this->option('reason') ? (string) $this->option('reason') : null;
        $metadata = $this->parseMetadata((array) $this->option('meta'));

        try {
            $merge = $this->mergeService->merge(
                canonicalPatientId: $canonicalPatientId,
                mergedPatientId: $mergedPatientId,
                duplicateCaseId: $duplicateCaseId,
                reason: $reason,
                actorId: auth()->id(),
                metadata: $metadata,
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
            ['Merge ID', 'Canonical', 'Merged', 'Status', 'Rewired Records', 'Conflict Tables'],
            [[
                $merge->id,
                $merge->canonical_patient_id,
                $merge->merged_patient_id,
                $merge->status,
                array_sum((array) $merge->rewire_summary),
                count((array) data_get($merge->metadata, 'conflicts', [])),
            ]]
        );

        $this->info('MPI merge completed.');

        return self::SUCCESS;
    }

    /**
     * @param  array<int, string>  $entries
     * @return array<string, string>
     */
    protected function parseMetadata(array $entries): array
    {
        $metadata = [];

        foreach ($entries as $entry) {
            $parts = explode('=', (string) $entry, 2);
            $key = trim($parts[0] ?? '');
            $value = trim($parts[1] ?? '');

            if ($key === '' || $value === '') {
                continue;
            }

            $metadata[$key] = $value;
        }

        return $metadata;
    }
}
