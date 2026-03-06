<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Patient;
use App\Models\User;
use App\Support\BranchAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class PatientAssignmentAuthorizer
{
    /**
     * @var list<string>
     */
    private const ASSIGNABLE_STAFF_ROLES = [
        'Admin',
        'Manager',
        'Doctor',
        'CSKH',
    ];

    /**
     * @return array<int, string>
     */
    public function assignableStaffOptions(?User $actor, ?int $branchId = null): array
    {
        return $this->scopeAssignableStaff(User::query(), $actor, $branchId)
            ->pluck('name', 'id')
            ->mapWithKeys(fn (mixed $name, mixed $id): array => [(int) $id => (string) $name])
            ->all();
    }

    public function scopeAssignableStaff(Builder $query, ?User $actor, ?int $branchId = null): Builder
    {
        $allowedBranchIds = $this->resolveAllowedBranchIds($actor, $branchId, 'assigned_to');

        if ($allowedBranchIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->whereHas('roles', fn (Builder $roleQuery): Builder => $roleQuery->whereIn('name', self::ASSIGNABLE_STAFF_ROLES))
            ->where(function (Builder $userQuery) use ($allowedBranchIds): void {
                $userQuery
                    ->whereIn('branch_id', $allowedBranchIds)
                    ->orWhereHas('activeDoctorBranchAssignments', function (Builder $assignmentQuery) use ($allowedBranchIds): void {
                        $assignmentQuery->whereIn('branch_id', $allowedBranchIds);
                    });
            })
            ->orderBy('name');
    }

    /**
     * @return array<int, string>
     */
    public function assignableDoctorOptions(?User $actor, ?int $branchId = null): array
    {
        return $this->scopeAssignableDoctors(User::query(), $actor, $branchId)
            ->pluck('name', 'id')
            ->mapWithKeys(fn (mixed $name, mixed $id): array => [(int) $id => (string) $name])
            ->all();
    }

    public function scopeAssignableDoctors(Builder $query, ?User $actor, ?int $branchId = null): Builder
    {
        $allowedBranchIds = $this->resolveAllowedBranchIds($actor, $branchId, 'primary_doctor_id');

        if ($allowedBranchIds === []) {
            return $query->whereRaw('1 = 0');
        }

        $doctorIds = collect($allowedBranchIds)
            ->flatMap(fn (int $allowedBranchId): array => app(DoctorBranchAssignmentService::class)->doctorIdsForBranch($allowedBranchId))
            ->unique()
            ->values()
            ->all();

        if ($doctorIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->role('Doctor')
            ->whereIn('id', $doctorIds)
            ->orderBy('name');
    }

    public function assertAssignableDoctorId(?User $actor, ?int $doctorId, ?int $branchId, string $field): ?int
    {
        if (! $actor instanceof User) {
            return $doctorId;
        }

        return $this->sanitizeAssignableDoctorId(
            actor: $actor,
            doctorId: $doctorId,
            branchId: $branchId,
            field: $field,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function sanitizeCustomerFormData(?User $actor, array $data, ?Customer $record = null): array
    {
        $data['branch_id'] = $this->sanitizeBranchId(
            actor: $actor,
            branchId: isset($data['branch_id']) && filled($data['branch_id'])
                ? (int) $data['branch_id']
                : BranchAccess::defaultBranchIdForCurrentUser(),
            field: 'branch_id',
            message: 'Bạn không thể tạo hoặc cập nhật khách hàng ở chi nhánh ngoài phạm vi được phân quyền.',
        );

        $data['assigned_to'] = $this->sanitizeAssignableStaffId(
            actor: $actor,
            staffId: isset($data['assigned_to']) && filled($data['assigned_to']) ? (int) $data['assigned_to'] : null,
            branchId: $data['branch_id'],
            field: 'assigned_to',
        );

        $this->assertUniqueCustomerContacts($data, $record);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function sanitizePatientFormData(?User $actor, array $data, ?Patient $record = null): array
    {
        $branchId = isset($data['first_branch_id']) && filled($data['first_branch_id'])
            ? (int) $data['first_branch_id']
            : null;

        $data['first_branch_id'] = $this->sanitizeBranchId(
            actor: $actor,
            branchId: $branchId,
            field: 'first_branch_id',
            message: 'Bạn không thể tạo hoặc cập nhật bệnh nhân ở chi nhánh ngoài phạm vi được phân quyền.',
            allowNull: true,
        );

        $data['owner_staff_id'] = $this->sanitizeAssignableStaffId(
            actor: $actor,
            staffId: isset($data['owner_staff_id']) && filled($data['owner_staff_id']) ? (int) $data['owner_staff_id'] : null,
            branchId: $data['first_branch_id'],
            field: 'owner_staff_id',
        );

        $data['primary_doctor_id'] = $this->sanitizeAssignableDoctorId(
            actor: $actor,
            doctorId: isset($data['primary_doctor_id']) && filled($data['primary_doctor_id']) ? (int) $data['primary_doctor_id'] : null,
            branchId: $data['first_branch_id'],
            field: 'primary_doctor_id',
        );

        $this->assertUniquePatientContacts($data, $record);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function assertUniqueCustomerContacts(array $data, ?Customer $record = null): void
    {
        if (! empty($data['email'])) {
            $emailHash = Customer::emailSearchHash((string) $data['email']);
            $exists = $emailHash !== null
                ? Customer::withTrashed()
                    ->when($record?->id, fn (Builder $query): Builder => $query->whereKeyNot((int) $record->id))
                    ->where('email_search_hash', $emailHash)
                    ->exists()
                : false;

            if ($exists) {
                throw ValidationException::withMessages([
                    'email' => 'Email đã tồn tại trong hệ thống.',
                ]);
            }
        }

        if (! empty($data['phone'])) {
            $phoneHash = Customer::phoneSearchHash((string) $data['phone']);
            $exists = $phoneHash !== null
                ? Customer::withTrashed()
                    ->when($record?->id, fn (Builder $query): Builder => $query->whereKeyNot((int) $record->id))
                    ->where('phone_search_hash', $phoneHash)
                    ->exists()
                : false;

            if ($exists) {
                throw ValidationException::withMessages([
                    'phone' => 'Số điện thoại đã tồn tại trong hệ thống.',
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function assertUniquePatientContacts(array $data, ?Patient $record = null): void
    {
        if (! empty($data['email'])) {
            $emailHash = Patient::emailSearchHash((string) $data['email']);
            $exists = $emailHash !== null
                ? Patient::withTrashed()
                    ->when($record?->id, fn (Builder $query): Builder => $query->whereKeyNot((int) $record->id))
                    ->where('email_search_hash', $emailHash)
                    ->exists()
                : false;

            if ($exists) {
                throw ValidationException::withMessages([
                    'email' => 'Email bệnh nhân đã tồn tại.',
                ]);
            }
        }

        if (! empty($data['phone'])) {
            $phoneHash = Patient::phoneSearchHash((string) $data['phone']);
            $branchId = isset($data['first_branch_id']) && filled($data['first_branch_id'])
                ? (int) $data['first_branch_id']
                : null;

            $exists = $phoneHash !== null
                ? Patient::withTrashed()
                    ->when($record?->id, fn (Builder $query): Builder => $query->whereKeyNot((int) $record->id))
                    ->where('phone_search_hash', $phoneHash)
                    ->when(
                        $branchId,
                        fn (Builder $query): Builder => $query->where('first_branch_id', $branchId),
                        fn (Builder $query): Builder => $query->whereNull('first_branch_id'),
                    )
                    ->exists()
                : false;

            if ($exists) {
                throw ValidationException::withMessages([
                    'phone' => 'Số điện thoại bệnh nhân đã tồn tại trong chi nhánh đã chọn.',
                ]);
            }
        }
    }

    protected function sanitizeAssignableStaffId(?User $actor, ?int $staffId, ?int $branchId, string $field): ?int
    {
        if ($staffId === null) {
            return null;
        }

        $isAllowed = $this->scopeAssignableStaff(User::query()->whereKey($staffId), $actor, $branchId)->exists();

        if (! $isAllowed) {
            throw ValidationException::withMessages([
                $field => 'Nhân sự được chọn không thuộc phạm vi chi nhánh được phép gán.',
            ]);
        }

        return $staffId;
    }

    protected function sanitizeAssignableDoctorId(?User $actor, ?int $doctorId, ?int $branchId, string $field): ?int
    {
        if ($doctorId === null) {
            return null;
        }

        $isAllowed = $this->scopeAssignableDoctors(User::query()->whereKey($doctorId), $actor, $branchId)->exists();

        if (! $isAllowed) {
            throw ValidationException::withMessages([
                $field => 'Bác sĩ được chọn không thuộc phạm vi chi nhánh được phép gán.',
            ]);
        }

        return $doctorId;
    }

    /**
     * @return array<int, int>
     */
    protected function resolveAllowedBranchIds(?User $actor, ?int $branchId, string $field): array
    {
        if (! $actor instanceof User) {
            return [];
        }

        if ($branchId !== null) {
            BranchAccess::assertCanAccessBranch(
                branchId: $branchId,
                field: $field,
                message: 'Bạn không có quyền gán dữ liệu sang chi nhánh ngoài phạm vi được phân quyền.',
            );

            return [$branchId];
        }

        return BranchAccess::accessibleBranchIds($actor, true);
    }

    protected function sanitizeBranchId(
        ?User $actor,
        ?int $branchId,
        string $field,
        string $message,
        bool $allowNull = false,
    ): ?int {
        if ($branchId === null) {
            if ($allowNull) {
                return null;
            }

            throw ValidationException::withMessages([
                $field => $message,
            ]);
        }

        BranchAccess::assertCanAccessBranch(
            branchId: $branchId,
            field: $field,
            message: $message,
        );

        return $branchId;
    }
}
