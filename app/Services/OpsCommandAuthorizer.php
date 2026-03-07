<?php

namespace App\Services;

use App\Support\ActionGate;
use App\Support\ActionPermission;
use Illuminate\Validation\ValidationException;

class OpsCommandAuthorizer
{
    public function authorize(string $message): ?int
    {
        ActionGate::authorize(
            ActionPermission::AUTOMATION_RUN,
            $message,
        );

        $user = auth()->user();

        if ($user === null) {
            if (app()->runningUnitTests()) {
                return null;
            }

            throw ValidationException::withMessages([
                'authorization' => $message,
            ]);
        }

        if (! $user->hasAnyRole(['Admin', 'AutomationService'])) {
            throw ValidationException::withMessages([
                'authorization' => 'Lệnh OPS chỉ cho phép Admin hoặc AutomationService thực thi.',
            ]);
        }

        $actorId = $user->getAuthIdentifier();

        return is_numeric($actorId) ? (int) $actorId : null;
    }
}
