<?php

namespace App\Filament\Resources\Patients\Relations;

use App\Models\Note;
use App\Models\User;
use App\Support\ClinicRuntimeSettings;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class PatientNotesRelationManager extends RelationManager
{
    protected static string $relationship = 'notes';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return 'Lịch chăm sóc';
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->schema($this->getUpdateCareFormSchema());
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('content')
            ->columns([
                Tables\Columns\TextColumn::make('care_at')
                    ->label('Thời gian chăm sóc')
                    ->getStateUsing(fn (Note $record) => $record->care_at ?? $record->created_at)
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('care_type')
                    ->label('Loại chăm sóc')
                    ->getStateUsing(fn (Note $record): string => $record->care_type ?: $this->mapLegacyType($record->type))
                    ->formatStateUsing(fn (?string $state): string => Arr::get($this->getCareTypeDisplayOptions(), $state, 'Khác')),

                Tables\Columns\BadgeColumn::make('care_channel')
                    ->label('Kênh chăm sóc')
                    ->getStateUsing(fn (Note $record): string => $record->care_channel ?: ClinicRuntimeSettings::defaultCareChannel())
                    ->formatStateUsing(fn (?string $state): string => Arr::get($this->getCareChannelDisplayOptions(), $state, 'Khác')),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Nhân viên chăm sóc')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\BadgeColumn::make('care_status')
                    ->label('Trạng thái chăm sóc')
                    ->getStateUsing(fn (Note $record): string => $record->care_status ?: Note::DEFAULT_CARE_STATUS)
                    ->formatStateUsing(fn (?string $state): string => Note::careStatusLabel($state))
                    ->color(fn (?string $state): string => Note::careStatusColor($state)),

                Tables\Columns\TextColumn::make('content')
                    ->label('Nội dung')
                    ->limit(100)
                    ->wrap(),
            ])
            ->filters([
                Filter::make('care_at')
                    ->label('Thời gian chăm sóc')
                    ->form([
                        DatePicker::make('from')->label('Ngày bắt đầu'),
                        DatePicker::make('until')->label('Ngày kết thúc'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (! empty($data['from'])) {
                            $query->whereDate('care_at', '>=', $data['from']);
                        }

                        if (! empty($data['until'])) {
                            $query->whereDate('care_at', '<=', $data['until']);
                        }

                        return $query;
                    }),
                SelectFilter::make('care_type')
                    ->label('Loại chăm sóc')
                    ->options($this->getCareTypeOptions()),
                SelectFilter::make('care_status')
                    ->label('Trạng thái chăm sóc')
                    ->options($this->getCareStatusOptions())
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (! $value) {
                            return $query;
                        }

                        return $query->whereIn('care_status', Note::statusesForQuery([$value]));
                    }),
                SelectFilter::make('care_channel')
                    ->label('Kênh chăm sóc')
                    ->options($this->getCareChannelOptions()),
                SelectFilter::make('user_id')
                    ->label('Nhân viên chăm sóc')
                    ->options($this->getCareStaffOptions()),
            ])
            ->headerActions([
                Action::make('createCareNow')
                    ->label('Thêm chăm sóc')
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->modalHeading('Tạo mới chăm sóc')
                    ->modalSubmitActionLabel('Lưu thông tin')
                    ->modalCancelActionLabel('Hủy bỏ')
                    ->form($this->getImmediateCareFormSchema())
                    ->action(function (array $data): void {
                        $this->getRelationship()->create($this->buildCreatePayload($data, 'immediate'));
                    }),
                Action::make('scheduleCare')
                    ->label('Đặt lịch chăm sóc')
                    ->icon('heroicon-o-clock')
                    ->color('warning')
                    ->modalHeading('Đặt lịch chăm sóc')
                    ->modalSubmitActionLabel('Lưu thông tin')
                    ->modalCancelActionLabel('Hủy bỏ')
                    ->form($this->getScheduledCareFormSchema())
                    ->action(function (array $data): void {
                        $this->getRelationship()->create($this->buildCreatePayload($data, 'scheduled'));
                    }),
            ])
            ->actions([
                Action::make('editCare')
                    ->label('Sửa')
                    ->icon('heroicon-o-pencil-square')
                    ->iconButton()
                    ->color('gray')
                    ->disabled(fn (Note $record): bool => $this->isCareLocked($record))
                    ->tooltip(fn (Note $record): string => $this->isCareLocked($record) ? 'Bản ghi đã hoàn thành, không thể chỉnh sửa.' : 'Cập nhật lịch chăm sóc')
                    ->modalHeading('Cập nhật lịch chăm sóc')
                    ->modalSubmitActionLabel('Lưu thông tin')
                    ->modalCancelActionLabel('Hủy bỏ')
                    ->fillForm(fn (Note $record): array => $this->getRecordFormState($record))
                    ->form($this->getUpdateCareFormSchema())
                    ->action(function (Note $record, array $data): void {
                        $record->update($this->buildUpdatePayload($record, $data));
                    }),
                Action::make('deleteCare')
                    ->label('Xóa')
                    ->icon('heroicon-o-trash')
                    ->iconButton()
                    ->color('danger')
                    ->disabled(fn (Note $record): bool => $this->isCareLocked($record))
                    ->tooltip(fn (Note $record): string => $this->isCareLocked($record) ? 'Bản ghi đã hoàn thành, không thể xóa.' : 'Xóa lịch chăm sóc')
                    ->requiresConfirmation()
                    ->modalHeading('Thông báo xác nhận')
                    ->modalDescription('Bạn muốn xoá lịch chăm sóc không ?')
                    ->modalSubmitActionLabel('Xác nhận')
                    ->modalCancelActionLabel('Hủy bỏ')
                    ->action(fn (Note $record): bool => (bool) $record->delete()),
            ])
            ->defaultSort('care_at', 'desc')
            ->emptyStateHeading('Chưa có lịch sử chăm sóc')
            ->emptyStateDescription('Thêm chăm sóc mới để theo dõi lịch sử tương tác với bệnh nhân.');
    }

    protected function getImmediateCareFormSchema(): array
    {
        return [
            DateTimePicker::make('care_at')
                ->label('Thời gian')
                ->default(now())
                ->seconds(false)
                ->required(),
            Select::make('care_channel')
                ->label('Kênh chăm sóc')
                ->options($this->getCareChannelOptions())
                ->default($this->normalizeCareChannel(ClinicRuntimeSettings::defaultCareChannel()))
                ->required(),
            Select::make('care_status')
                ->label('Trạng thái')
                ->options($this->getImmediateCareStatusOptions())
                ->default(Note::CARE_STATUS_DONE)
                ->required(),
            Select::make('care_type')
                ->label('Loại chăm sóc')
                ->options($this->getCareTypeOptions())
                ->default('other')
                ->required(),
            TextInput::make('care_staff_display')
                ->label('Nhân viên chăm sóc')
                ->default(fn (): string => (string) (auth()->user()?->name ?? '-'))
                ->disabled()
                ->dehydrated(false),
            Textarea::make('content')
                ->label('Nội dung chăm sóc')
                ->rows(4)
                ->required()
                ->columnSpanFull(),
        ];
    }

    protected function getScheduledCareFormSchema(): array
    {
        return [
            DateTimePicker::make('care_at')
                ->label('Thời gian')
                ->default(now()->addHour())
                ->seconds(false)
                ->required(),
            Select::make('care_type')
                ->label('Loại chăm sóc')
                ->options($this->getCareTypeOptions())
                ->default('appointment_reminder')
                ->required(),
            Select::make('user_id')
                ->label('Nhân viên chăm sóc')
                ->options($this->getCareStaffOptions())
                ->searchable()
                ->required(),
            Checkbox::make('is_recurring')
                ->label('Chăm sóc định kỳ')
                ->default(false),
            Textarea::make('content')
                ->label('Nội dung chăm sóc')
                ->rows(4)
                ->required()
                ->columnSpanFull(),
        ];
    }

    protected function getUpdateCareFormSchema(): array
    {
        return [
            Hidden::make('care_mode'),
            DateTimePicker::make('care_at')
                ->label('Thời gian')
                ->seconds(false)
                ->required(),
            Select::make('care_type')
                ->label('Loại chăm sóc')
                ->options($this->getCareTypeOptions())
                ->required(),
            Select::make('user_id')
                ->label('Nhân viên chăm sóc')
                ->options($this->getCareStaffOptions())
                ->searchable()
                ->required(),
            Radio::make('care_status')
                ->label('Trạng thái chăm sóc')
                ->options($this->getUpdatableCareStatusOptions())
                ->required(),
            Select::make('care_channel')
                ->label('Kênh chăm sóc')
                ->options($this->getCareChannelOptions())
                ->default($this->normalizeCareChannel(ClinicRuntimeSettings::defaultCareChannel()))
                ->required(),
            Checkbox::make('is_recurring')
                ->label('Chăm sóc định kỳ')
                ->visible(fn (callable $get): bool => $get('care_mode') === 'scheduled'),
            Textarea::make('content')
                ->label('Nội dung chăm sóc')
                ->rows(4)
                ->required()
                ->columnSpanFull(),
        ];
    }

    protected function buildCreatePayload(array $data, string $mode): array
    {
        $resolvedMode = $mode === 'scheduled' ? 'scheduled' : 'immediate';
        $careStatus = $resolvedMode === 'scheduled'
            ? Note::CARE_STATUS_NOT_STARTED
            : ($data['care_status'] ?? Note::CARE_STATUS_DONE);

        return [
            'customer_id' => $this->getOwnerRecord()->customer_id,
            'user_id' => $resolvedMode === 'scheduled'
                ? ($data['user_id'] ?? auth()->id())
                : auth()->id(),
            'type' => Note::TYPE_GENERAL,
            'care_type' => $this->normalizeCareType($data['care_type'] ?? null),
            'care_channel' => $this->normalizeCareChannel($data['care_channel'] ?? null),
            'care_status' => $careStatus,
            'care_at' => $data['care_at'] ?? now(),
            'care_mode' => $resolvedMode,
            'is_recurring' => (bool) ($data['is_recurring'] ?? false),
            'content' => trim((string) ($data['content'] ?? '')),
            'source_type' => 'patient_care',
            'source_id' => $this->getOwnerRecord()->id,
        ];
    }

    protected function buildUpdatePayload(Note $record, array $data): array
    {
        return [
            'customer_id' => $this->getOwnerRecord()->customer_id,
            'user_id' => $data['user_id'] ?? $record->user_id ?? auth()->id(),
            'type' => Note::TYPE_GENERAL,
            'care_type' => $this->normalizeCareType($data['care_type'] ?? $record->care_type ?? null),
            'care_channel' => $this->normalizeCareChannel($data['care_channel'] ?? $record->care_channel ?? null),
            'care_status' => $data['care_status'] ?? $record->care_status ?? Note::CARE_STATUS_NOT_STARTED,
            'care_at' => $data['care_at'] ?? $record->care_at ?? now(),
            'care_mode' => $data['care_mode'] ?? $record->care_mode ?? 'immediate',
            'is_recurring' => (bool) ($data['is_recurring'] ?? $record->is_recurring ?? false),
            'content' => trim((string) ($data['content'] ?? $record->content ?? '')),
            'source_type' => $record->source_type ?? 'patient_care',
            'source_id' => $record->source_id ?? $this->getOwnerRecord()->id,
        ];
    }

    protected function getRecordFormState(Note $record): array
    {
        return [
            'care_at' => $record->care_at ?? $record->created_at,
            'care_type' => $this->normalizeCareType($record->care_type ?: $this->mapLegacyType($record->type)),
            'care_channel' => $this->normalizeCareChannel($record->care_channel ?: ClinicRuntimeSettings::defaultCareChannel()),
            'care_status' => $record->care_status ?: Note::DEFAULT_CARE_STATUS,
            'care_mode' => $record->care_mode ?: ($record->care_status === Note::CARE_STATUS_NOT_STARTED ? 'scheduled' : 'immediate'),
            'is_recurring' => (bool) $record->is_recurring,
            'user_id' => $record->user_id ?: auth()->id(),
            'content' => $record->content,
        ];
    }

    protected function isCareLocked(Note $record): bool
    {
        return ($record->care_status ?? Note::DEFAULT_CARE_STATUS) === Note::CARE_STATUS_DONE;
    }

    protected function getCareTypeOptions(): array
    {
        return [
            'warranty' => 'Bảo hành',
            'post_treatment_follow_up' => 'Hỏi thăm sau điều trị',
            'treatment_plan_follow_up' => 'Theo dõi chưa chốt kế hoạch',
            'appointment_reminder' => 'Nhắc lịch hẹn',
            'medication_reminder' => 'Nhắc lịch uống thuốc',
            'other' => 'Khác',
        ];
    }

    protected function getCareTypeDisplayOptions(): array
    {
        return $this->getCareTypeOptions() + [
            'birthday_care' => 'Chăm sóc sinh nhật',
            'general_care' => 'Chăm sóc chung',
        ];
    }

    protected function getCareChannelOptions(): array
    {
        return Arr::only(ClinicRuntimeSettings::careChannelOptions(), ['message', 'call', 'chat', 'gift']);
    }

    protected function getCareChannelDisplayOptions(): array
    {
        return $this->getCareChannelOptions() + Arr::except(
            ClinicRuntimeSettings::careChannelOptions(),
            array_keys($this->getCareChannelOptions()),
        );
    }

    protected function getCareStatusOptions(): array
    {
        return Note::careStatusOptions();
    }

    protected function getImmediateCareStatusOptions(): array
    {
        return Arr::only($this->getCareStatusOptions(), [
            Note::CARE_STATUS_DONE,
            Note::CARE_STATUS_NEED_FOLLOWUP,
            Note::CARE_STATUS_FAILED,
        ]);
    }

    protected function getUpdatableCareStatusOptions(): array
    {
        return Arr::only($this->getCareStatusOptions(), [
            Note::CARE_STATUS_NOT_STARTED,
            Note::CARE_STATUS_IN_PROGRESS,
            Note::CARE_STATUS_DONE,
            Note::CARE_STATUS_NEED_FOLLOWUP,
            Note::CARE_STATUS_FAILED,
        ]);
    }

    protected function getCareStaffOptions(): array
    {
        return User::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    protected function mapLegacyType(?string $legacyType): string
    {
        return match ($legacyType) {
            'complaint' => 'appointment_reminder',
            'behavior', 'preference' => 'post_treatment_follow_up',
            default => 'other',
        };
    }

    protected function normalizeCareType(?string $value): string
    {
        $value = (string) $value;

        return array_key_exists($value, $this->getCareTypeOptions()) ? $value : 'other';
    }

    protected function normalizeCareChannel(?string $value): string
    {
        $value = (string) $value;

        if (array_key_exists($value, $this->getCareChannelOptions())) {
            return $value;
        }

        $default = ClinicRuntimeSettings::defaultCareChannel();

        return array_key_exists($default, $this->getCareChannelOptions()) ? $default : 'call';
    }
}
