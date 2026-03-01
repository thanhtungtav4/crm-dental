<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use App\Models\Customer;
use App\Support\BranchAccess;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CreateCustomer extends CreateRecord
{
    protected static string $resource = CustomerResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // If creator is Manager or if branch not chosen, default to creator's branch
        $user = Auth::user();
        if ($user) {
            $isManager = method_exists($user, 'hasRole') && $user->hasRole('Manager');
            if ($isManager || empty($data['branch_id'])) {
                $data['branch_id'] = $user->branch_id;
            }
        }

        if (is_numeric($data['branch_id'] ?? null)) {
            BranchAccess::assertCanAccessBranch(
                branchId: (int) $data['branch_id'],
                field: 'branch_id',
                message: 'Bạn không thể tạo khách hàng ở chi nhánh ngoài phạm vi được phân quyền.',
            );
        }

        if (! empty($data['email'])) {
            $exists = Customer::withTrashed()->where('email', $data['email'])->exists();
            if ($exists) {
                throw ValidationException::withMessages([
                    'email' => 'Email đã tồn tại trong hệ thống.',
                ]);
            }
        }
        if (! empty($data['phone'])) {
            $exists = Customer::withTrashed()->where('phone', $data['phone'])->exists();
            if ($exists) {
                throw ValidationException::withMessages([
                    'phone' => 'Số điện thoại đã tồn tại trong hệ thống.',
                ]);
            }
        }

        return $data;
    }
}
