<?php

namespace App\Filament\Resources\InstallmentPlans\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DateTimePicker;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class InstallmentPlansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // Invoice number with patient
                TextColumn::make('invoice.invoice_no')
                    ->label('Hóa đơn')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->invoice?->patient?->full_name ?? 'N/A'),

                // Total amount
                TextColumn::make('total_amount')
                    ->label('Tổng tiền')
                    ->money('VND', divideBy: 1)
                    ->sortable()
                    ->weight('bold'),

                // Installment plan display (amount x number)
                TextColumn::make('installment_display')
                    ->label('Kế hoạch')
                    ->formatStateUsing(fn ($record) => 
                        number_format($record->installment_amount, 0, ',', '.') . 'đ × ' . 
                        $record->number_of_installments . ' kỳ'
                    )
                    ->description(fn ($record) => $record->getFrequencyLabel())
                    ->searchable(false),

                // Payment progress with bar
                TextColumn::make('payment_progress')
                    ->label('Tiến độ')
                    ->formatStateUsing(fn ($record) => 
                        number_format($record->paid_amount, 0, ',', '.') . 'đ / ' . 
                        number_format($record->total_amount, 0, ',', '.') . 'đ'
                    )
                    ->description(fn ($record) => $record->getCompletionPercentage() . '%')
                    ->color(fn ($record) => match(true) {
                        $record->getCompletionPercentage() >= 100 => 'success',
                        $record->getCompletionPercentage() >= 50 => 'info',
                        default => 'warning',
                    }),

                // Remaining amount
                TextColumn::make('remaining_amount')
                    ->label('Còn lại')
                    ->money('VND', divideBy: 1)
                    ->sortable()
                    ->color(fn ($record) => $record->remaining_amount > 0 ? 'danger' : 'success')
                    ->weight(fn ($record) => $record->remaining_amount > 0 ? 'bold' : 'normal'),

                // Next due date
                TextColumn::make('next_due')
                    ->label('Kỳ tiếp theo')
                    ->formatStateUsing(function ($record) {
                        $nextDue = $record->getNextDueInstallment();
                        if (!$nextDue) {
                            return '—';
                        }
                        $dueDate = \Carbon\Carbon::parse($nextDue['due_date']);
                        $daysUntil = now()->diffInDays($dueDate, false);
                        
                        if ($daysUntil < 0) {
                            return '⚠️ Quá hạn ' . abs($daysUntil) . ' ngày';
                        } elseif ($daysUntil <= 7) {
                            return '⏰ ' . $dueDate->format('d/m/Y') . ' (còn ' . $daysUntil . ' ngày)';
                        }
                        return $dueDate->format('d/m/Y');
                    })
                    ->color(function ($record) {
                        $nextDue = $record->getNextDueInstallment();
                        if (!$nextDue) return 'gray';
                        $daysUntil = now()->diffInDays(\Carbon\Carbon::parse($nextDue['due_date']), false);
                        return match(true) {
                            $daysUntil < 0 => 'danger',
                            $daysUntil <= 7 => 'warning',
                            default => 'success',
                        };
                    }),

                // Status badge
                BadgeColumn::make('status')
                    ->label('Trạng thái')
                    ->formatStateUsing(fn ($record) => $record->getStatusLabel())
                    ->color(fn ($record) => $record->getStatusBadgeColor()),

                // Payment frequency
                BadgeColumn::make('payment_frequency')
                    ->label('Tần suất')
                    ->formatStateUsing(fn ($record) => $record->getFrequencyLabel())
                    ->color('info')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Ngày tạo')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                // Filter by status
                SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options([
                        'active' => 'Đang hoạt động',
                        'completed' => 'Hoàn thành',
                        'defaulted' => 'Nợ quá hạn',
                        'cancelled' => 'Đã hủy',
                    ])
                    ->multiple(),

                // Filter by payment frequency
                SelectFilter::make('payment_frequency')
                    ->label('Tần suất')
                    ->options([
                        'monthly' => 'Hàng tháng',
                        'weekly' => 'Hàng tuần',
                        'custom' => 'Tùy chỉnh',
                    ])
                    ->multiple(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Xem'),
                
                EditAction::make()
                    ->label('Sửa'),
                
                // Record payment action
                Action::make('record_payment')
                    ->label('Thanh toán')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->form([
                        TextInput::make('amount')
                            ->label('Số tiền thanh toán')
                            ->required()
                            ->numeric()
                            ->prefix('VNĐ')
                            ->suffix('đ')
                            ->minValue(1)
                            ->default(fn ($record) => $record->installment_amount),
                        
                        Select::make('method')
                            ->label('Phương thức')
                            ->options([
                                'cash' => 'Tiền mặt',
                                'card' => 'Thẻ',
                                'transfer' => 'Chuyển khoản',
                            ])
                            ->default('cash')
                            ->required(),
                        
                        DateTimePicker::make('paid_at')
                            ->label('Thời gian')
                            ->default(now())
                            ->required()
                            ->native(false),
                    ])
                    ->action(function ($record, array $data) {
                        // Record payment
                        $payment = $record->invoice->recordPayment(
                            $data['amount'],
                            $data['method'],
                            'Thanh toán trả góp kỳ ' . (count(array_filter($record->schedule ?? [], fn($s) => $s['status'] === 'paid')) + 1)
                        );
                        
                        // Update installment plan
                        $record->recordInstallmentPayment($data['amount']);
                        
                        Notification::make()
                            ->success()
                            ->title('Thanh toán thành công')
                            ->body('Đã ghi nhận thanh toán ' . number_format($data['amount'], 0, ',', '.') . 'đ')
                            ->send();
                    })
                    ->visible(fn ($record) => $record->status === 'active' && $record->remaining_amount > 0),
                
                // View schedule action
                Action::make('view_schedule')
                    ->label('Xem lịch')
                    ->icon('heroicon-o-calendar')
                    ->color('info')
                    ->modalHeading('Lịch trả góp')
                    ->modalContent(fn ($record) => view('filament.modals.installment-schedule', ['plan' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Đóng'),
                
                // Mark as defaulted
                Action::make('mark_defaulted')
                    ->label('Đánh dấu nợ quá hạn')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update(['status' => 'defaulted']);
                        Notification::make()
                            ->warning()
                            ->title('Đã đánh dấu nợ quá hạn')
                            ->send();
                    })
                    ->visible(fn ($record) => $record->isDefaulted() && $record->status === 'active'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Xóa đã chọn'),
                    
                    \Filament\Actions\BulkAction::make('mark_defaulted')
                        ->label('Đánh dấu nợ quá hạn')
                        ->icon('heroicon-o-exclamation-triangle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $records->each->update(['status' => 'defaulted']);
                            Notification::make()
                                ->warning()
                                ->title('Đã cập nhật trạng thái')
                                ->body('Đã đánh dấu ' . $records->count() . ' kế hoạch là nợ quá hạn')
                                ->send();
                        }),
                ]),
            ])
            ->emptyStateHeading('Chưa có kế hoạch trả góp')
            ->emptyStateDescription('Tạo kế hoạch trả góp mới cho hóa đơn')
            ->emptyStateIcon('heroicon-o-credit-card');
    }
}
