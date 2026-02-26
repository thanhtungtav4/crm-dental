<?php

namespace App\Support;

class ActionPermission
{
    public const PAYMENT_REVERSAL = 'Action:PaymentReversal';

    public const APPOINTMENT_OVERRIDE = 'Action:AppointmentOverride';

    public const PLAN_APPROVAL = 'Action:PlanApproval';

    public const AUTOMATION_RUN = 'Action:AutomationRun';

    public const MASTER_DATA_SYNC = 'Action:MasterDataSync';

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
        ];
    }
}
