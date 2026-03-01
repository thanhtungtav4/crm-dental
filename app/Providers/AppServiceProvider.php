<?php

namespace App\Providers;

use App\Models\Appointment;
use App\Models\ClinicalNote;
use App\Models\ClinicalOrder;
use App\Models\ClinicalResult;
use App\Models\ClinicSetting;
use App\Models\Consent;
use App\Models\InsuranceClaim;
use App\Models\Note;
use App\Models\Patient;
use App\Models\PatientMedicalRecord;
use App\Models\Payment;
use App\Models\PlanItem;
use App\Models\Prescription;
use App\Models\TreatmentPlan;
use App\Models\TreatmentSession;
use App\Observers\AppointmentObserver;
use App\Observers\ClinicalNoteVersionObserver;
use App\Observers\ClinicalOrderObserver;
use App\Observers\ClinicalResultObserver;
use App\Observers\ConsentObserver;
use App\Observers\InsuranceClaimObserver;
use App\Observers\NoteObserver;
use App\Observers\PatientMedicalRecordObserver;
use App\Observers\PatientObserver;
use App\Observers\PaymentObserver;
use App\Observers\PlanItemObserver;
use App\Observers\PrescriptionObserver;
use App\Observers\TreatmentPlanObserver;
use App\Observers\TreatmentSessionAuditObserver;
use App\Observers\TreatmentSessionObserver;
use Filament\Actions\Action as FilamentAction;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureFilamentActionNotifications();

        RateLimiter::for('web-leads', function (Request $request): Limit {
            $configuredRate = (int) ClinicSetting::getValue(
                'web_lead.rate_limit_per_minute',
                config('services.web_lead.rate_limit_per_minute', 60),
            );

            $perMinute = min(max($configuredRate, 1), 1000);
            $tokenFingerprint = (string) ($request->bearerToken() ?: $request->header('X-Web-Lead-Token') ?: 'anonymous');

            return Limit::perMinute($perMinute)
                ->by(sha1($tokenFingerprint.'|'.$request->ip()));
        });

        RateLimiter::for('api-mobile', function (Request $request): Limit {
            $tokenId = optional($request->user()?->currentAccessToken())->id;

            return Limit::perMinute(120)
                ->by(($tokenId ? 'token:'.$tokenId : 'ip:'.$request->ip()));
        });

        // Register Appointment Observer
        Appointment::observe(AppointmentObserver::class);
        ClinicalNote::observe(ClinicalNoteVersionObserver::class);
        ClinicalOrder::observe(ClinicalOrderObserver::class);
        ClinicalResult::observe(ClinicalResultObserver::class);
        Note::observe(NoteObserver::class);
        PlanItem::observe(PlanItemObserver::class);
        Patient::observe(PatientObserver::class);
        PatientMedicalRecord::observe(PatientMedicalRecordObserver::class);
        Payment::observe(PaymentObserver::class);
        Prescription::observe(PrescriptionObserver::class);
        TreatmentPlan::observe(TreatmentPlanObserver::class);
        TreatmentSession::observe(TreatmentSessionObserver::class);
        TreatmentSession::observe(TreatmentSessionAuditObserver::class);
        Consent::observe(ConsentObserver::class);
        InsuranceClaim::observe(InsuranceClaimObserver::class);
    }

    private function configureFilamentActionNotifications(): void
    {
        FilamentAction::configureUsing(function (FilamentAction $action): void {
            $action
                ->failureNotificationTitle(fn (FilamentAction $action): string => self::resolveActionFailureNotificationTitle($action))
                ->unauthorizedNotificationTitle('Bạn không có quyền thực hiện thao tác này.')
                ->rateLimitedNotificationTitle('Bạn thao tác quá nhanh, vui lòng thử lại sau.');
        });
    }

    private static function resolveActionFailureNotificationTitle(FilamentAction $action): string
    {
        return 'Không thể xử lý: '.self::resolveActionLabel($action);
    }

    private static function resolveActionLabel(FilamentAction $action): string
    {
        $label = trim(strip_tags((string) $action->getLabel()));

        return $label !== '' ? $label : 'thao tác';
    }
}
