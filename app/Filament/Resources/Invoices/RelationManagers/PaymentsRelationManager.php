<?php

namespace App\Filament\Resources\Invoices\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';
    
    protected static ?string $title = 'Lịch sử thanh toán';
    
    protected static ?string $modelLabel = 'thanh toán';
    
    protected static ?string $pluralModelLabel = 'thanh toán';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Thông tin thanh toán')
                    ->schema([
                        TextInput::make('amount')
                            ->label('Số tiền')
                            ->numeric()
                            ->required()
                            ->prefix('VNĐ')
                            ->suffix('đ')
                            ->minValue(0)
                            ->default(fn () => $this->getOwnerRecord()->calculateBalance())
                            ->helperText(fn () => 'Còn lại: ' . number_format($this->getOwnerRecord()->calculateBalance(), 0, ',', '.') . 'đ'),
                        
                        Select::make('method')
                            ->label('Phương thức')
                            ->required()
                            ->options([
                                'cash' => '💵 Tiền mặt',
                                'card' => '💳 Thẻ tín dụng/ghi nợ',
                                'transfer' => '🏦 Chuyển khoản',
                                'other' => '📝 Khác',
                            ])
                            ->default('cash')
                            ->native(false)
                            ->reactive()
                            ->columnSpan(1),
                        
                        DateTimePicker::make('paid_at')
                            ->label('Ngày thanh toán')
                            ->required()
                            ->default(now())
                            ->format('d/m/Y H:i')
                            ->native(false)
                            ->columnSpan(1),
                    ])
                    ->columns(2),
                
                Section::make('Chi tiết giao dịch')
                    ->schema([
                        TextInput::make('transaction_ref')
                            ->label('Mã giao dịch')
                            ->maxLength(255)
                            ->visible(fn ($get) => in_array($get('method'), ['card', 'transfer']))
                            ->helperText('Mã tham chiếu từ ngân hàng hoặc cổng thanh toán'),
                        
                        Select::make('payment_source')
                            ->label('Nguồn thanh toán')
                            ->options([
                                'patient' => '👤 Bệnh nhân',
                                'insurance' => '🏥 Bảo hiểm',
                                'other' => '📄 Khác',
                            ])
                            ->default('patient')
                            ->native(false)
                            ->reactive(),
                        
                        TextInput::make('insurance_claim_number')
                            ->label('Số hồ sơ bảo hiểm')
                            ->maxLength(255)
                            ->visible(fn ($get) => $get('payment_source') === 'insurance'),
                    ])
                    ->collapsible()
                    ->collapsed(),
                
                Section::make('Người nhận & Ghi chú')
                    ->schema([
                        Select::make('received_by')
                            ->label('Người nhận')
                            ->relationship('receiver', 'name')
                            ->searchable()
                            ->preload()
                            ->default(auth()->id()),
                        
                        Textarea::make('note')
                            ->label('Ghi chú')
                            ->rows(3)
                            ->maxLength(500),
                    ])
                    ->collapsible()
                    ->collapsed(),
                
                Hidden::make('invoice_id')
                    ->default(fn () => $this->getOwnerRecord()->id),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('amount')
                    ->label('Số tiền')
                    ->money('VND')
                    ->weight('bold')
                    ->color(fn ($record) => $record->getMethodBadgeColor())
                    ->sortable(),
                
                BadgeColumn::make('method')
                    ->label('Phương thức')
                    ->formatStateUsing(fn ($record) => $record->getMethodLabel())
                    ->icon(fn ($record) => $record->getMethodIcon())
                    ->color(fn ($record) => $record->getMethodBadgeColor()),
                
                BadgeColumn::make('payment_source')
                    ->label('Nguồn')
                    ->formatStateUsing(fn ($record) => $record->getSourceLabel())
                    ->color(fn ($record) => $record->getSourceBadgeColor())
                    ->toggleable(),
                
                TextColumn::make('paid_at')
                    ->label('Ngày thanh toán')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->description(fn ($record) => $record->created_at->diffForHumans()),
                
                TextColumn::make('receiver.name')
                    ->label('Người nhận')
                    ->toggleable(isToggledHiddenByDefault: true),
                
                TextColumn::make('transaction_ref')
                    ->label('Mã GD')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                
                TextColumn::make('note')
                    ->label('Ghi chú')
                    ->limit(30)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('method')
                    ->label('Phương thức')
                    ->multiple()
                    ->options([
                        'cash' => '💵 Tiền mặt',
                        'card' => '💳 Thẻ',
                        'transfer' => '🏦 Chuyển khoản',
                        'other' => '📝 Khác',
                    ]),
                
                SelectFilter::make('payment_source')
                    ->label('Nguồn')
                    ->multiple()
                    ->options([
                        'patient' => '👤 Bệnh nhân',
                        'insurance' => '🏥 Bảo hiểm',
                        'other' => '📄 Khác',
                    ]),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Tạo thanh toán')
                    ->icon(Heroicon::OutlinedPlus)
                    ->after(function () {
                        $this->getOwnerRecord()->updatePaidAmount();
                    }),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Xem'),
                DeleteAction::make()
                    ->label('Xóa')
                    ->after(function () {
                        $this->getOwnerRecord()->updatePaidAmount();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Xóa đã chọn')
                        ->after(function () {
                            $this->getOwnerRecord()->updatePaidAmount();
                        }),
                ]),
            ])
            ->defaultSort('paid_at', 'desc')
            ->emptyStateHeading('Chưa có thanh toán')
            ->emptyStateDescription('Tạo thanh toán đầu tiên cho hóa đơn này')
            ->emptyStateIcon('heroicon-o-banknotes')
            ->heading(function () {
                $record = $this->getOwnerRecord();
                $paid = number_format($record->getTotalPaid(), 0, ',', '.');
                $total = number_format($record->total_amount, 0, ',', '.');
                $balance = number_format($record->calculateBalance(), 0, ',', '.');
                $progress = round($record->getPaymentProgress(), 1);
                
                return "Lịch sử thanh toán • Đã thu: {$paid}đ / {$total}đ ({$progress}%) • Còn lại: {$balance}đ";
            });
    }
}
