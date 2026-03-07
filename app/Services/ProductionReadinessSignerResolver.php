<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class ProductionReadinessSignerResolver
{
    /**
     * @return array{user:User|null, issue:string|null}
     */
    public function resolve(string $identifier, string $role): array
    {
        $normalized = Str::lower(trim($identifier));

        if ($normalized === '') {
            return [
                'user' => null,
                'issue' => 'thieu_nguoi_ky_'.Str::lower($role),
            ];
        }

        $user = $this->resolveUser($normalized);

        if (! $user instanceof User) {
            return [
                'user' => null,
                'issue' => 'khong_tim_thay_nguoi_ky_'.Str::lower($role),
            ];
        }

        if (array_key_exists('status', $user->getAttributes()) && ! (bool) $user->status) {
            return [
                'user' => null,
                'issue' => 'nguoi_ky_'.Str::lower($role).'_khong_hoat_dong',
            ];
        }

        if (! $user->hasAnyRole($this->allowedRoles())) {
            return [
                'user' => null,
                'issue' => 'nguoi_ky_'.Str::lower($role).'_khong_dung_vai_tro',
            ];
        }

        return [
            'user' => $user,
            'issue' => null,
        ];
    }

    /**
     * @return list<string>
     */
    public function allowedRoles(): array
    {
        return ['Admin', 'Manager'];
    }

    protected function resolveUser(string $normalized): ?User
    {
        $emailMatch = User::query()
            ->whereRaw('LOWER(email) = ?', [$normalized])
            ->first();

        if ($emailMatch instanceof User) {
            return $emailMatch;
        }

        $nameMatches = User::query()
            ->whereRaw('LOWER(name) = ?', [$normalized])
            ->get();

        if (! $nameMatches instanceof Collection || $nameMatches->count() !== 1) {
            return null;
        }

        return $nameMatches->first();
    }
}
