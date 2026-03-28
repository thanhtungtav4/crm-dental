<?php

namespace App\Models;

use App\Casts\NullableEncrypted;
use App\Services\PatientConversionService;
use App\Support\BranchAccess;
use App\Support\IdentitySearchHash;
use App\Support\PatientIdentityNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use RuntimeException;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'branch_id',
        'full_name',
        'phone',
        'phone_normalized',
        'phone_search_hash',
        'email',
        'email_search_hash',
        'birthday',
        'gender',
        'address',
        'source',
        'source_detail',
        'customer_group_id',
        'promotion_group_id',
        'status',
        'assigned_to',
        'next_follow_up_at',
        'last_contacted_at',
        'last_web_contact_at',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'birthday' => 'date',
        'last_contacted_at' => 'datetime',
        'next_follow_up_at' => 'datetime',
        'last_web_contact_at' => 'datetime',
        'phone' => NullableEncrypted::class,
        'email' => NullableEncrypted::class,
        'address' => NullableEncrypted::class,
    ];

    protected static function booted(): void
    {
        static::saving(function (self $customer): void {
            $customer->phone_search_hash = static::phoneSearchHash($customer->phone);
            $customer->email_search_hash = static::emailSearchHash($customer->email);
            $customer->phone_normalized = null;

            if (! is_numeric($customer->branch_id)) {
                return;
            }

            BranchAccess::assertCanAccessBranch(
                branchId: (int) $customer->branch_id,
                field: 'branch_id',
                message: 'Bạn không có quyền thao tác khách hàng ở chi nhánh này.',
            );
        });
    }

    public static function phoneSearchHash(?string $phone): ?string
    {
        return IdentitySearchHash::phone('customer', $phone);
    }

    public static function emailSearchHash(?string $email): ?string
    {
        return IdentitySearchHash::email('customer', $email);
    }

    public function scopeWherePhoneMatches(Builder $query, ?string $phone): Builder
    {
        $phoneHash = static::phoneSearchHash($phone);

        if ($phoneHash === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('phone_search_hash', $phoneHash);
    }

    public function scopeWhereEmailMatches(Builder $query, ?string $email): Builder
    {
        $emailHash = static::emailSearchHash($email);

        if ($emailHash === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('email_search_hash', $emailHash);
    }

    public static function normalizePhoneForSearch(?string $phone): ?string
    {
        return PatientIdentityNormalizer::normalizePhone($phone);
    }

    public static function normalizeEmailForSearch(?string $email): ?string
    {
        return PatientIdentityNormalizer::normalizeEmail($email);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function patient()
    {
        return $this->hasOne(Patient::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function customerGroup()
    {
        return $this->belongsTo(CustomerGroup::class);
    }

    public function promotionGroup()
    {
        return $this->belongsTo(PromotionGroup::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function notes()
    {
        return $this->hasMany(Note::class);
    }

    public function convertToPatient(): Patient
    {
        /** @var PatientConversionService $service */
        $service = app(PatientConversionService::class);
        $patient = $service->convert($this);

        if (! $patient) {
            throw new RuntimeException('Không thể chuyển đổi khách hàng thành bệnh nhân.');
        }

        return $patient;
    }
}
