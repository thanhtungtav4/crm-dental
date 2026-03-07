<?php

namespace App\Services;

use App\Models\ClinicSetting;
use App\Models\ClinicSettingLog;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class IntegrationSecretRotationService
{
    private const DEFAULT_GRACE_MINUTES = 1440;

    /**
     * @var array<string, array{
     *     display_name: string,
     *     group: string,
     *     label: string,
     *     grace_minutes_key: string,
     *     previous_secret_key: string,
     *     grace_expires_at_key: string,
     *     rotated_at_key: string,
     *     rotated_by_key: string,
     *     rotation_reason_key: string,
     *     grace_revoked_at_key: string,
     *     default_grace_minutes: int
     * }>
     */
    private const ROTATABLE_SECRETS = [
        'web_lead.api_token' => [
            'display_name' => 'Web Lead API Token',
            'group' => 'web_lead',
            'label' => 'API Token',
            'grace_minutes_key' => 'web_lead.api_token_grace_minutes',
            'previous_secret_key' => 'web_lead.api_token_previous_secret',
            'grace_expires_at_key' => 'web_lead.api_token_grace_expires_at',
            'rotated_at_key' => 'web_lead.api_token_rotated_at',
            'rotated_by_key' => 'web_lead.api_token_rotated_by',
            'rotation_reason_key' => 'web_lead.api_token_rotation_reason',
            'grace_revoked_at_key' => 'web_lead.api_token_grace_revoked_at',
            'default_grace_minutes' => self::DEFAULT_GRACE_MINUTES,
        ],
        'zalo.webhook_token' => [
            'display_name' => 'Zalo Webhook Verify Token',
            'group' => 'zalo',
            'label' => 'Webhook Verify Token',
            'grace_minutes_key' => 'zalo.webhook_token_grace_minutes',
            'previous_secret_key' => 'zalo.webhook_token_previous_secret',
            'grace_expires_at_key' => 'zalo.webhook_token_grace_expires_at',
            'rotated_at_key' => 'zalo.webhook_token_rotated_at',
            'rotated_by_key' => 'zalo.webhook_token_rotated_by',
            'rotation_reason_key' => 'zalo.webhook_token_rotation_reason',
            'grace_revoked_at_key' => 'zalo.webhook_token_grace_revoked_at',
            'default_grace_minutes' => self::DEFAULT_GRACE_MINUTES,
        ],
        'emr.api_key' => [
            'display_name' => 'EMR API Key',
            'group' => 'emr',
            'label' => 'API Key',
            'grace_minutes_key' => 'emr.api_key_grace_minutes',
            'previous_secret_key' => 'emr.api_key_previous_secret',
            'grace_expires_at_key' => 'emr.api_key_grace_expires_at',
            'rotated_at_key' => 'emr.api_key_rotated_at',
            'rotated_by_key' => 'emr.api_key_rotated_by',
            'rotation_reason_key' => 'emr.api_key_rotation_reason',
            'grace_revoked_at_key' => 'emr.api_key_grace_revoked_at',
            'default_grace_minutes' => self::DEFAULT_GRACE_MINUTES,
        ],
    ];

    public function isRotatable(string $settingKey): bool
    {
        return array_key_exists($settingKey, self::ROTATABLE_SECRETS);
    }

    public function matches(string $settingKey, string $incomingToken): bool
    {
        $normalizedIncomingToken = trim($incomingToken);

        if ($normalizedIncomingToken === '') {
            return false;
        }

        $activeToken = trim((string) ClinicSetting::getValue($settingKey, ''));

        if ($activeToken !== '' && hash_equals($activeToken, $normalizedIncomingToken)) {
            return true;
        }

        $activeGraceState = $this->activeGraceState($settingKey);

        if ($activeGraceState === null) {
            return false;
        }

        $graceToken = trim((string) ($activeGraceState['previous_secret'] ?? ''));

        return $graceToken !== '' && hash_equals($graceToken, $normalizedIncomingToken);
    }

    /**
     * @return array{
     *     rotated: bool,
     *     grace_applied: bool,
     *     initialized: bool,
     *     revoked_existing: bool,
     *     grace_expires_at: ?string,
     *     grace_minutes: int,
     *     display_name: string
     * }
     */
    public function rotate(
        string $settingKey,
        string $newSecret,
        ?int $actorId = null,
        ?string $reason = null,
    ): array {
        $descriptor = $this->descriptor($settingKey);
        $normalizedNewSecret = trim($newSecret);
        $currentSecret = trim((string) ClinicSetting::getValue($settingKey, ''));
        $rotationReason = $reason ?: 'Secret rotated via IntegrationSettings page.';
        $graceMinutes = $this->graceMinutes($settingKey);
        $result = [
            'rotated' => false,
            'grace_applied' => false,
            'initialized' => false,
            'revoked_existing' => false,
            'grace_expires_at' => null,
            'grace_minutes' => $graceMinutes,
            'display_name' => $descriptor['display_name'],
        ];

        if ($normalizedNewSecret === $currentSecret) {
            return $result;
        }

        $activeSetting = ClinicSetting::setValue($settingKey, $normalizedNewSecret, [
            'group' => $descriptor['group'],
            'label' => $descriptor['label'],
            'value_type' => 'text',
            'is_secret' => true,
            'is_active' => true,
            'sort_order' => 9990,
        ]);

        $result['rotated'] = true;

        if ($normalizedNewSecret === '') {
            $result['revoked_existing'] = $this->clearGraceState($descriptor);
            $this->persistRotationMetadata(
                descriptor: $descriptor,
                actorId: $actorId,
                reason: $rotationReason,
                rotatedAt: now(),
                graceExpiresAt: null,
            );
            $this->logRotation(
                setting: $activeSetting,
                oldValue: $currentSecret !== '' ? '••••••' : '(trống)',
                newValue: '(trống)',
                reason: $rotationReason,
                actorId: $actorId,
                context: [
                    'rotation_mode' => 'secret_cleared',
                    'grace_minutes' => $graceMinutes,
                ],
            );

            return $result;
        }

        if ($currentSecret === '') {
            $result['initialized'] = true;
            $result['revoked_existing'] = $this->clearGraceState($descriptor);
            $this->persistRotationMetadata(
                descriptor: $descriptor,
                actorId: $actorId,
                reason: $rotationReason,
                rotatedAt: now(),
                graceExpiresAt: null,
            );
            $this->logRotation(
                setting: $activeSetting,
                oldValue: '(trống)',
                newValue: '••••••',
                reason: $rotationReason,
                actorId: $actorId,
                context: [
                    'rotation_mode' => 'secret_initialized',
                    'grace_minutes' => $graceMinutes,
                ],
            );

            return $result;
        }

        $graceExpiresAt = now()->addMinutes($graceMinutes);

        ClinicSetting::setValue($descriptor['previous_secret_key'], $currentSecret, [
            'group' => $descriptor['group'],
            'label' => $descriptor['display_name'].' (old grace token)',
            'value_type' => 'text',
            'is_secret' => true,
            'is_active' => true,
            'sort_order' => 9991,
        ]);

        ClinicSetting::setValue($descriptor['grace_expires_at_key'], $graceExpiresAt->toISOString(), [
            'group' => $descriptor['group'],
            'label' => $descriptor['display_name'].' grace expiry',
            'value_type' => 'text',
            'is_active' => true,
            'sort_order' => 9992,
        ]);

        $this->persistRotationMetadata(
            descriptor: $descriptor,
            actorId: $actorId,
            reason: $rotationReason,
            rotatedAt: now(),
            graceExpiresAt: $graceExpiresAt,
        );

        $this->logRotation(
            setting: $activeSetting,
            oldValue: '••••••',
            newValue: '••••••',
            reason: $rotationReason,
            actorId: $actorId,
            context: [
                'rotation_mode' => 'grace_window_started',
                'grace_minutes' => $graceMinutes,
                'grace_expires_at' => $graceExpiresAt->toISOString(),
            ],
        );

        $result['grace_applied'] = true;
        $result['grace_expires_at'] = $graceExpiresAt->toISOString();

        return $result;
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{
     *     key: string,
     *     display_name: string,
     *     grace_expires_at: string,
     *     rotated_at: ?string,
     *     rotated_by: ?int,
     *     rotation_reason: ?string,
     *     remaining_minutes: int
     * }>
     */
    public function activeGraceRotations(): Collection
    {
        return collect(array_keys(self::ROTATABLE_SECRETS))
            ->map(function (string $settingKey): ?array {
                $state = $this->activeGraceState($settingKey);

                if ($state === null) {
                    return null;
                }

                return [
                    'key' => $settingKey,
                    'display_name' => (string) $state['display_name'],
                    'grace_expires_at' => (string) $state['grace_expires_at'],
                    'rotated_at' => data_get($state, 'rotated_at'),
                    'rotated_by' => data_get($state, 'rotated_by'),
                    'rotation_reason' => data_get($state, 'rotation_reason'),
                    'remaining_minutes' => max(0, now()->diffInMinutes(CarbonImmutable::parse((string) $state['grace_expires_at']), false)),
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @return array{
     *     total_expired: int,
     *     revoked: int,
     *     items: array<int, array{
     *         key: string,
     *         display_name: string,
     *         expired_at: string
     *     }>
     * }
     */
    public function revokeExpired(bool $dryRun = false, ?int $actorId = null): array
    {
        $summary = [
            'total_expired' => 0,
            'revoked' => 0,
            'items' => [],
        ];

        foreach (self::ROTATABLE_SECRETS as $settingKey => $descriptor) {
            $expiredState = $this->expiredGraceState($settingKey);

            if ($expiredState === null) {
                continue;
            }

            $summary['total_expired']++;
            $summary['items'][] = [
                'key' => $settingKey,
                'display_name' => $descriptor['display_name'],
                'expired_at' => (string) $expiredState['grace_expires_at'],
            ];

            if ($dryRun) {
                continue;
            }

            $activeSetting = ClinicSetting::query()
                ->where('key', $settingKey)
                ->first();

            $this->clearGraceState($descriptor);
            ClinicSetting::setValue($descriptor['grace_revoked_at_key'], now()->toISOString(), [
                'group' => $descriptor['group'],
                'label' => $descriptor['display_name'].' grace revoked at',
                'value_type' => 'text',
                'is_active' => true,
                'sort_order' => 9996,
            ]);

            if ($activeSetting instanceof ClinicSetting) {
                $this->logRotation(
                    setting: $activeSetting,
                    oldValue: '••••••',
                    newValue: 'grace token revoked',
                    reason: 'Expired secret grace window has been revoked automatically.',
                    actorId: $actorId,
                    context: [
                        'rotation_mode' => 'grace_window_revoked',
                        'expired_at' => (string) $expiredState['grace_expires_at'],
                    ],
                );
            }

            $summary['revoked']++;
        }

        return $summary;
    }

    public function graceMinutes(string $settingKey): int
    {
        $descriptor = $this->descriptor($settingKey);

        return max(
            5,
            min(
                10080,
                (int) ClinicSetting::getValue(
                    $descriptor['grace_minutes_key'],
                    $descriptor['default_grace_minutes'],
                ),
            ),
        );
    }

    /**
     * @return array{display_name:string,previous_secret:string,grace_expires_at:string,rotated_at:?string,rotated_by:?int,rotation_reason:?string}|null
     */
    public function activeGraceState(string $settingKey): ?array
    {
        $descriptor = $this->descriptor($settingKey);
        $previousSecret = trim((string) ClinicSetting::getValue($descriptor['previous_secret_key'], ''));
        $graceExpiresAt = $this->parseTimestamp(
            (string) ClinicSetting::getValue($descriptor['grace_expires_at_key'], ''),
        );

        if ($previousSecret === '' || ! $graceExpiresAt instanceof CarbonInterface || $graceExpiresAt->isPast()) {
            return null;
        }

        return [
            'display_name' => $descriptor['display_name'],
            'previous_secret' => $previousSecret,
            'grace_expires_at' => $graceExpiresAt->toISOString(),
            'rotated_at' => $this->parseTimestamp((string) ClinicSetting::getValue($descriptor['rotated_at_key'], ''))?->toISOString(),
            'rotated_by' => ($rotatedBy = ClinicSetting::getValue($descriptor['rotated_by_key'], null)) !== null ? (int) $rotatedBy : null,
            'rotation_reason' => ($reason = trim((string) ClinicSetting::getValue($descriptor['rotation_reason_key'], ''))) !== '' ? $reason : null,
        ];
    }

    /**
     * @return array{
     *     display_name: string,
     *     grace_expires_at: string
     * }|null
     */
    protected function expiredGraceState(string $settingKey): ?array
    {
        $descriptor = $this->descriptor($settingKey);
        $previousSecret = trim((string) ClinicSetting::getValue($descriptor['previous_secret_key'], ''));
        $graceExpiresAt = $this->parseTimestamp(
            (string) ClinicSetting::getValue($descriptor['grace_expires_at_key'], ''),
        );

        if ($previousSecret === '' || ! $graceExpiresAt instanceof CarbonInterface || $graceExpiresAt->isFuture()) {
            return null;
        }

        return [
            'display_name' => $descriptor['display_name'],
            'grace_expires_at' => $graceExpiresAt->toISOString(),
        ];
    }

    /**
     * @param  array{
     *     display_name: string,
     *     group: string,
     *     label: string,
     *     grace_minutes_key: string,
     *     previous_secret_key: string,
     *     grace_expires_at_key: string,
     *     rotated_at_key: string,
     *     rotated_by_key: string,
     *     rotation_reason_key: string,
     *     grace_revoked_at_key: string,
     *     default_grace_minutes: int
     * }  $descriptor
     */
    protected function persistRotationMetadata(
        array $descriptor,
        ?int $actorId,
        string $reason,
        CarbonInterface $rotatedAt,
        ?CarbonInterface $graceExpiresAt,
    ): void {
        ClinicSetting::setValue($descriptor['rotated_at_key'], $rotatedAt->toISOString(), [
            'group' => $descriptor['group'],
            'label' => $descriptor['display_name'].' rotated at',
            'value_type' => 'text',
            'is_active' => true,
            'sort_order' => 9993,
        ]);

        ClinicSetting::setValue($descriptor['rotated_by_key'], $actorId, [
            'group' => $descriptor['group'],
            'label' => $descriptor['display_name'].' rotated by',
            'value_type' => 'text',
            'is_active' => true,
            'sort_order' => 9994,
        ]);

        ClinicSetting::setValue($descriptor['rotation_reason_key'], $reason, [
            'group' => $descriptor['group'],
            'label' => $descriptor['display_name'].' rotation reason',
            'value_type' => 'text',
            'is_active' => true,
            'sort_order' => 9995,
        ]);

        ClinicSetting::setValue($descriptor['grace_revoked_at_key'], null, [
            'group' => $descriptor['group'],
            'label' => $descriptor['display_name'].' grace revoked at',
            'value_type' => 'text',
            'is_active' => true,
            'sort_order' => 9996,
        ]);

        if ($graceExpiresAt === null) {
            ClinicSetting::setValue($descriptor['grace_expires_at_key'], null, [
                'group' => $descriptor['group'],
                'label' => $descriptor['display_name'].' grace expiry',
                'value_type' => 'text',
                'is_active' => true,
                'sort_order' => 9992,
            ]);
        }
    }

    /**
     * @param  array{
     *     display_name: string,
     *     group: string,
     *     label: string,
     *     grace_minutes_key: string,
     *     previous_secret_key: string,
     *     grace_expires_at_key: string,
     *     rotated_at_key: string,
     *     rotated_by_key: string,
     *     rotation_reason_key: string,
     *     grace_revoked_at_key: string,
     *     default_grace_minutes: int
     * }  $descriptor
     */
    protected function clearGraceState(array $descriptor): bool
    {
        $hadGraceSecret = trim((string) ClinicSetting::getValue($descriptor['previous_secret_key'], '')) !== '';

        ClinicSetting::setValue($descriptor['previous_secret_key'], null, [
            'group' => $descriptor['group'],
            'label' => $descriptor['display_name'].' (old grace token)',
            'value_type' => 'text',
            'is_secret' => true,
            'is_active' => true,
            'sort_order' => 9991,
        ]);

        ClinicSetting::setValue($descriptor['grace_expires_at_key'], null, [
            'group' => $descriptor['group'],
            'label' => $descriptor['display_name'].' grace expiry',
            'value_type' => 'text',
            'is_active' => true,
            'sort_order' => 9992,
        ]);

        return $hadGraceSecret;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function logRotation(
        ClinicSetting $setting,
        string $oldValue,
        string $newValue,
        string $reason,
        ?int $actorId,
        array $context = [],
    ): void {
        if (! Schema::hasTable('clinic_setting_logs')) {
            return;
        }

        $payload = [
            'clinic_setting_id' => $setting->id,
            'setting_group' => $setting->group ?? 'integration',
            'setting_key' => $setting->key,
            'setting_label' => $setting->label,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'is_secret' => true,
            'changed_by' => $actorId,
            'changed_at' => now(),
        ];

        if (Schema::hasColumn('clinic_setting_logs', 'change_reason')) {
            $payload['change_reason'] = $reason;
        }

        if (Schema::hasColumn('clinic_setting_logs', 'context')) {
            $payload['context'] = $context;
        }

        ClinicSettingLog::query()->create($payload);
    }

    /**
     * @return array{
     *     display_name: string,
     *     group: string,
     *     label: string,
     *     grace_minutes_key: string,
     *     previous_secret_key: string,
     *     grace_expires_at_key: string,
     *     rotated_at_key: string,
     *     rotated_by_key: string,
     *     rotation_reason_key: string,
     *     grace_revoked_at_key: string,
     *     default_grace_minutes: int
     * }
     */
    protected function descriptor(string $settingKey): array
    {
        /** @var array{
         *     display_name: string,
         *     group: string,
         *     label: string,
         *     grace_minutes_key: string,
         *     previous_secret_key: string,
         *     grace_expires_at_key: string,
         *     rotated_at_key: string,
         *     rotated_by_key: string,
         *     rotation_reason_key: string,
         *     grace_revoked_at_key: string,
         *     default_grace_minutes: int
         * } $descriptor
         */
        $descriptor = self::ROTATABLE_SECRETS[$settingKey] ?? [];

        if ($descriptor === []) {
            throw new \InvalidArgumentException("Unsupported rotatable integration secret [{$settingKey}].");
        }

        return $descriptor;
    }

    protected function parseTimestamp(string $value): ?CarbonImmutable
    {
        $normalizedValue = trim($value);

        if ($normalizedValue === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($normalizedValue);
        } catch (\Throwable) {
            return null;
        }
    }
}
