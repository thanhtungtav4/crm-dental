<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Support\ActionGate;
use App\Support\ActionPermission;
use App\Support\ClinicRuntimeSettings;
use Illuminate\Console\Command;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Throwable;

class RunScheduledCommand extends Command
{
    protected $signature = 'ops:run-scheduled-command
        {target : Artisan command cần chạy}
        {--target-args=* : Arguments / options truyền vào command đích}
        {--timeout= : Timeout mỗi lần chạy (giây)}
        {--max-attempts= : Số lần retry tối đa}
        {--retry-delay= : Thời gian chờ giữa các lần retry (giây)}
        {--alert-after= : Ngưỡng SLA runtime để tạo alert (giây)}';

    protected $description = 'Wrapper scheduler command với timeout/retry/alert và audit log.';

    public function handle(): int
    {
        ActionGate::authorize(
            ActionPermission::AUTOMATION_RUN,
            'Bạn không có quyền chạy scheduler automation.',
        );

        $targetCommand = trim((string) $this->argument('target'));

        if ($targetCommand === '' || $targetCommand === $this->getName()) {
            $this->error('Target command không hợp lệ.');

            return self::FAILURE;
        }

        $targetArgs = collect((array) $this->option('target-args'))
            ->map(fn (mixed $value): string => trim((string) $value))
            ->filter(fn (string $value): bool => $value !== '')
            ->values()
            ->all();

        $timeoutSeconds = $this->resolveIntOption(
            option: 'timeout',
            default: ClinicRuntimeSettings::schedulerCommandTimeoutSeconds(),
            min: 10,
        );
        $maxAttempts = $this->resolveIntOption(
            option: 'max-attempts',
            default: ClinicRuntimeSettings::schedulerCommandMaxAttempts(),
            min: 1,
        );
        $retryDelaySeconds = $this->resolveIntOption(
            option: 'retry-delay',
            default: ClinicRuntimeSettings::schedulerCommandRetryDelaySeconds(),
            min: 0,
        );
        $alertAfterSeconds = $this->resolveIntOption(
            option: 'alert-after',
            default: ClinicRuntimeSettings::schedulerCommandAlertAfterSeconds(),
            min: 0,
        );

        $targetDisplay = $targetCommand;
        if ($targetArgs !== []) {
            $targetDisplay .= ' '.implode(' ', $targetArgs);
        }

        $attempt = 0;

        while ($attempt < $maxAttempts) {
            $attempt++;
            $startedAt = microtime(true);
            $result = null;
            $exception = null;
            $timedOut = false;

            try {
                $result = Process::path(base_path())
                    ->timeout($timeoutSeconds)
                    ->run(array_merge([
                        PHP_BINARY,
                        base_path('artisan'),
                        $targetCommand,
                    ], $targetArgs));
            } catch (ProcessTimedOutException $throwable) {
                $timedOut = true;
                $exception = $throwable;
                $result = $throwable->result;
            } catch (Throwable $throwable) {
                $exception = $throwable;
            }

            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
            $exitCode = $result?->exitCode();
            $output = trim((string) ($result?->output() ?? ''));
            $errorOutput = trim((string) ($result?->errorOutput() ?? ''));
            $succeeded = ! $timedOut && $exception === null && $result !== null && $result->successful();
            $isSlow = $durationMs >= ($alertAfterSeconds * 1000);

            $metadata = [
                'target_command' => $targetCommand,
                'target_args' => $targetArgs,
                'attempt' => $attempt,
                'max_attempts' => $maxAttempts,
                'timeout_seconds' => $timeoutSeconds,
                'retry_delay_seconds' => $retryDelaySeconds,
                'alert_after_seconds' => $alertAfterSeconds,
                'duration_ms' => $durationMs,
                'exit_code' => $exitCode,
                'timed_out' => $timedOut,
                'exception' => $exception?->getMessage(),
                'output' => Str::limit($output, 1500),
                'error_output' => Str::limit($errorOutput, 1500),
            ];

            if ($succeeded) {
                AuditLog::record(
                    entityType: AuditLog::ENTITY_AUTOMATION,
                    entityId: 0,
                    action: AuditLog::ACTION_RUN,
                    actorId: auth()->id(),
                    metadata: $metadata,
                );

                if ($isSlow) {
                    $this->recordAlert(
                        metadata: array_merge($metadata, [
                            'alert_reason' => 'sla_breach',
                            'will_retry' => false,
                        ]),
                    );
                }

                $this->info(
                    "Scheduler command thành công: {$targetDisplay} ".
                    "(attempt {$attempt}/{$maxAttempts}, {$durationMs}ms).",
                );

                return self::SUCCESS;
            }

            $willRetry = $attempt < $maxAttempts;
            $alertReason = $timedOut ? 'timeout' : 'command_failed';

            $this->recordAlert(
                metadata: array_merge($metadata, [
                    'alert_reason' => $alertReason,
                    'will_retry' => $willRetry,
                ]),
            );

            if ($willRetry && $retryDelaySeconds > 0) {
                sleep($retryDelaySeconds);
            }
        }

        $this->error("Scheduler command thất bại sau {$maxAttempts} lần chạy: {$targetDisplay}.");

        return self::FAILURE;
    }

    protected function resolveIntOption(string $option, int $default, int $min = 0): int
    {
        $rawValue = $this->option($option);
        $resolved = is_numeric($rawValue) ? (int) $rawValue : $default;

        return max($min, $resolved);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    protected function recordAlert(array $metadata): void
    {
        AuditLog::record(
            entityType: AuditLog::ENTITY_AUTOMATION,
            entityId: 0,
            action: AuditLog::ACTION_FAIL,
            actorId: auth()->id(),
            metadata: $metadata,
        );
    }
}
