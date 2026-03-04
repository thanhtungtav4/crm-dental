<?php

namespace App\Filament\Resources\PopupAnnouncements\Schemas;

use App\Models\PopupAnnouncement;
use App\Support\BranchAccess;
use App\Support\ClinicRuntimeSettings;
use Filament\Forms;
use Filament\Schemas\Schema;
use Spatie\Permission\Models\Role;

class PopupAnnouncementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('code')
                    ->label('Mã popup')
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit'),

                Forms\Components\TextInput::make('title')
                    ->label('Tiêu đề')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Select::make('priority')
                    ->label('Mức ưu tiên')
                    ->options(PopupAnnouncement::priorityOptions())
                    ->default(PopupAnnouncement::PRIORITY_INFO)
                    ->required(),

                Forms\Components\Select::make('status')
                    ->label('Trạng thái')
                    ->options(PopupAnnouncement::statusOptions())
                    ->default(PopupAnnouncement::STATUS_DRAFT)
                    ->required(),

                Forms\Components\DateTimePicker::make('starts_at')
                    ->label('Bắt đầu hiển thị')
                    ->native(false)
                    ->seconds(false)
                    ->nullable(),

                Forms\Components\DateTimePicker::make('ends_at')
                    ->label('Kết thúc hiển thị')
                    ->native(false)
                    ->seconds(false)
                    ->nullable(),

                Forms\Components\Toggle::make('require_ack')
                    ->label('Bắt buộc xác nhận đã đọc')
                    ->default(false)
                    ->inline(false),

                Forms\Components\Toggle::make('show_once')
                    ->label('Hiển thị 1 lần cho mỗi user')
                    ->default(true)
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Luôn bật để tránh popup lặp gây nhiễu thao tác.'),

                Forms\Components\CheckboxList::make('target_role_names')
                    ->label('Nhóm quyền nhận popup')
                    ->options(fn (): array => Role::query()->orderBy('name')->pluck('name', 'name')->all())
                    ->columns(3)
                    ->required()
                    ->helperText('Popup chỉ gửi tới user thuộc các nhóm quyền đã chọn.')
                    ->columnSpanFull(),

                Forms\Components\Select::make('target_branch_ids')
                    ->label('Chi nhánh nhận popup')
                    ->options(fn (): array => BranchAccess::branchOptionsForCurrentUser())
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->helperText('Để trống = gửi toàn hệ thống. Theo policy hiện tại chỉ role được cấu hình mới gửi toàn hệ thống.')
                    ->columnSpanFull(),

                Forms\Components\RichEditor::make('message')
                    ->label('Nội dung popup')
                    ->toolbarButtons([
                        ['bold', 'italic', 'underline', 'strike', 'link'],
                        ['h2', 'h3', 'alignStart', 'alignCenter', 'alignEnd'],
                        ['blockquote', 'bulletList', 'orderedList'],
                        ['table', 'attachFiles'],
                        ['undo', 'redo'],
                    ])
                    ->fileAttachmentsDisk('public')
                    ->fileAttachmentsDirectory('popup-announcements')
                    ->fileAttachmentsVisibility('public')
                    ->helperText('Hỗ trợ chèn ảnh, bảng và định dạng nâng cao. Ảnh được lưu tại storage/public/popup-announcements.')
                    ->required()
                    ->columnSpanFull(),

                Forms\Components\Placeholder::make('sender_roles_note')
                    ->label('Quyền gửi toàn hệ thống')
                    ->content(function (): string {
                        $roles = ClinicRuntimeSettings::popupAnnouncementSenderRoles();

                        return $roles === []
                            ? 'Chưa cấu hình role gửi popup toàn hệ thống.'
                            : 'Role được gửi toàn hệ thống: '.implode(', ', $roles);
                    })
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }
}
