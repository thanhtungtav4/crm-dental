<?php

namespace App\Support;

class ActionPermission
{
    public const PAYMENT_REVERSAL = 'Action:PaymentReversal';

    public const APPOINTMENT_OVERRIDE = 'Action:AppointmentOverride';

    public const PLAN_APPROVAL = 'Action:PlanApproval';

    public const AUTOMATION_RUN = 'Action:AutomationRun';

    public const MASTER_DATA_SYNC = 'Action:MasterDataSync';

    public const INSURANCE_CLAIM_DECISION = 'Action:InsuranceClaimDecision';

    public const MPI_DEDUPE_REVIEW = 'Action:MpiDedupeReview';

    public const PATIENT_BRANCH_TRANSFER = 'Action:PatientBranchTransfer';

    public const EMR_CLINICAL_WRITE = 'Action:EmrClinicalWrite';

    public const EMR_RECORD_EXPORT = 'Action:EmrRecordExport';

    public const EMR_SYNC_PUSH = 'Action:EmrSyncPush';

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::PAYMENT_REVERSAL,
            self::APPOINTMENT_OVERRIDE,
            self::PLAN_APPROVAL,
            self::AUTOMATION_RUN,
            self::MASTER_DATA_SYNC,
            self::INSURANCE_CLAIM_DECISION,
            self::MPI_DEDUPE_REVIEW,
            self::PATIENT_BRANCH_TRANSFER,
            self::EMR_CLINICAL_WRITE,
            self::EMR_RECORD_EXPORT,
            self::EMR_SYNC_PUSH,
        ];
    }
}
