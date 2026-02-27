<?php

namespace App\Services;

use App\Models\User;
use App\Support\ClinicRuntimeSettings;
use Spatie\Permission\PermissionRegistrar;

class AutomationActorResolver
{
    /**
     * @return array{
     *     ok:bool,
     *     actor_id:int|null,
     *     actor:?User,
     *     permission:string,
     *     required_role:string,
     *     roles:array<int, string>,
     *     issues:array<int, array{severity:string, code:string, message:string}>
     * }
     */
    public function healthReport(string $permission, bool $enforceRequiredRole = true): array
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $issues = [];
        $actorId = ClinicRuntimeSettings::schedulerAutomationActorUserId();
        $requiredRole = trim(ClinicRuntimeSettings::schedulerAutomationActorRequiredRole());

        if ($actorId === null) {
            $issues[] = [
                'severity' => 'error',
                'code' => 'actor_not_configured',
                'message' => 'Chưa cấu hình scheduler.automation_actor_user_id.',
            ];

            return [
                'ok' => false,
                'actor_id' => null,
                'actor' => null,
                'permission' => $permission,
                'required_role' => $requiredRole,
                'roles' => [],
                'issues' => $issues,
            ];
        }

        $actor = User::query()->find($actorId);

        if (! $actor) {
            $issues[] = [
                'severity' => 'error',
                'code' => 'actor_not_found',
                'message' => "Không tìm thấy user #{$actorId} cho scheduler actor.",
            ];
        } else {
            $roles = $actor->getRoleNames()->values()->all();

            if (
                $enforceRequiredRole
                && $requiredRole !== ''
                && ! $actor->hasRole($requiredRole)
            ) {
                $issues[] = [
                    'severity' => 'error',
                    'code' => 'missing_required_role',
                    'message' => "Scheduler actor phải có role {$requiredRole}.",
                ];
            }

            if (! $actor->can($permission)) {
                $issues[] = [
                    'severity' => 'error',
                    'code' => 'missing_permission',
                    'message' => "Scheduler actor thiếu quyền {$permission}.",
                ];
            }

            $privilegedRoles = collect($roles)
                ->intersect(['Admin', 'Manager', 'Doctor', 'CSKH'])
                ->values()
                ->all();

            if (
                $enforceRequiredRole
                && $requiredRole !== ''
                && $privilegedRoles !== []
            ) {
                $issues[] = [
                    'severity' => 'warning',
                    'code' => 'privileged_roles_attached',
                    'message' => 'Scheduler actor đang có role vận hành: '.implode(', ', $privilegedRoles).'.',
                ];
            }

            return [
                'ok' => collect($issues)->every(fn (array $issue): bool => $issue['severity'] !== 'error'),
                'actor_id' => (int) $actor->id,
                'actor' => $actor,
                'permission' => $permission,
                'required_role' => $requiredRole,
                'roles' => $roles,
                'issues' => $issues,
            ];
        }

        return [
            'ok' => false,
            'actor_id' => $actorId,
            'actor' => null,
            'permission' => $permission,
            'required_role' => $requiredRole,
            'roles' => [],
            'issues' => $issues,
        ];
    }

    public function resolveForPermission(string $permission, bool $enforceRequiredRole = true): ?User
    {
        $report = $this->healthReport(
            permission: $permission,
            enforceRequiredRole: $enforceRequiredRole,
        );

        $actor = $report['actor'] ?? null;

        if (! $report['ok'] || ! $actor instanceof User) {
            return null;
        }

        return $actor;
    }
}
