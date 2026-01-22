<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use App\Models\Customer;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;

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

        if (!empty($data['email'])) {
            $exists = Customer::withTrashed()->where('email', $data['email'])->exists();
            if ($exists) {
                throw ValidationException::withMessages([
                    'email' => 'Email đã tồn tại trong hệ thống.',
                ]);
            }
        }
        if (!empty($data['phone'])) {
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
