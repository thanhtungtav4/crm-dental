<?php

namespace App\Filament\Pages\Auth;

use App\Models\AuditLog;
use App\Support\ClinicRuntimeSettings;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    public function authenticate(): ?LoginResponse
    {
        $data = $this->form->getState();
        $maxAttempts = ClinicRuntimeSettings::securityLoginMaxAttempts();
        $lockoutSeconds = ClinicRuntimeSettings::securityLoginLockoutMinutes() * 60;
        $lockoutComponent = $this->lockoutComponentKey(
            email: (string) ($data['email'] ?? ''),
        );

        try {
            $this->rateLimit(
                maxAttempts: $maxAttempts,
                decaySeconds: $lockoutSeconds,
                method: 'authenticate',
                component: $lockoutComponent,
            );
        } catch (TooManyRequestsException $exception) {
            $this->recordLockoutAudit(
                email: (string) ($data['email'] ?? ''),
                maxAttempts: $maxAttempts,
                secondsUntilAvailable: $exception->secondsUntilAvailable,
            );
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        /** @var SessionGuard $authGuard */
        $authGuard = Filament::auth();
        $authProvider = $authGuard->getProvider(); /** @phpstan-ignore-line */
        $credentials = $this->getCredentialsFromFormData($data);
        $user = $authProvider->retrieveByCredentials($credentials);

        if ((! $user) || (! $authProvider->validateCredentials($user, $credentials))) {
            $this->userUndertakingMultiFactorAuthentication = null;

            $this->fireFailedEvent($authGuard, $user, $credentials);
            $this->throwFailureValidationException();
        }

        if (
            filled($this->userUndertakingMultiFactorAuthentication) &&
            (decrypt($this->userUndertakingMultiFactorAuthentication) === $user->getAuthIdentifier())
        ) {
            $this->multiFactorChallengeForm->validate();
        } else {
            foreach (Filament::getMultiFactorAuthenticationProviders() as $multiFactorAuthenticationProvider) {
                if (! $multiFactorAuthenticationProvider->isEnabled($user)) {
                    continue;
                }

                $this->userUndertakingMultiFactorAuthentication = encrypt($user->getAuthIdentifier());

                if (method_exists($multiFactorAuthenticationProvider, 'beforeChallenge')) {
                    $multiFactorAuthenticationProvider->beforeChallenge($user);
                }

                break;
            }

            if (filled($this->userUndertakingMultiFactorAuthentication)) {
                $this->multiFactorChallengeForm->fill();

                return null;
            }
        }

        if (! $authGuard->attemptWhen($credentials, function (Authenticatable $user): bool {
            if (! ($user instanceof FilamentUser)) {
                return true;
            }

            return $user->canAccessPanel(Filament::getCurrentOrDefaultPanel());
        }, $data['remember'] ?? false)) {
            $this->fireFailedEvent($authGuard, $user, $credentials);
            $this->throwFailureValidationException();
        }

        session()->regenerate();
        $this->clearRateLimiter(method: 'authenticate', component: $lockoutComponent);
        $this->clearRateLimiter(method: 'authenticate');

        return app(LoginResponse::class);
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    protected function fireFailedEvent(Guard $guard, ?Authenticatable $user, array $credentials): void
    {
        event(app(Failed::class, [
            'guard' => property_exists($guard, 'name') ? $guard->name : '',
            'user' => $user,
            'credentials' => $credentials,
        ]));
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.email' => __('filament-panels::auth/pages/login.messages.failed'),
        ]);
    }

    protected function lockoutComponentKey(string $email): string
    {
        $normalizedEmail = mb_strtolower(trim($email));

        if ($normalizedEmail === '') {
            $normalizedEmail = '__blank__';
        }

        return static::class.'|'.$normalizedEmail;
    }

    protected function recordLockoutAudit(string $email, int $maxAttempts, int $secondsUntilAvailable): void
    {
        AuditLog::record(
            entityType: AuditLog::ENTITY_SECURITY,
            entityId: 0,
            action: AuditLog::ACTION_BLOCK,
            actorId: auth()->id(),
            metadata: [
                'reason' => 'login_lockout',
                'max_attempts' => $maxAttempts,
                'seconds_until_available' => $secondsUntilAvailable,
                'lockout_minutes' => ClinicRuntimeSettings::securityLoginLockoutMinutes(),
                'email_hash' => sha1(mb_strtolower(trim($email))),
                'ip' => request()->ip(),
            ],
        );
    }
}
