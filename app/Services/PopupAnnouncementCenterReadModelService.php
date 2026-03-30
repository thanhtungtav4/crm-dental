<?php

namespace App\Services;

use App\Models\PopupAnnouncement;
use App\Models\PopupAnnouncementDelivery;

class PopupAnnouncementCenterReadModelService
{
    public function pendingDeliveryForUser(int $userId): ?PopupAnnouncementDelivery
    {
        $delivery = PopupAnnouncementDelivery::query()
            ->with('announcement')
            ->forUser($userId)
            ->undone()
            ->whereHas('announcement', function ($query): void {
                $query
                    ->where('status', PopupAnnouncement::STATUS_PUBLISHED)
                    ->where(function ($nested): void {
                        $nested
                            ->whereNull('starts_at')
                            ->orWhere('starts_at', '<=', now());
                    })
                    ->where(function ($nested): void {
                        $nested
                            ->whereNull('ends_at')
                            ->orWhere('ends_at', '>', now());
                    });
            })
            ->orderBy('delivered_at')
            ->orderBy('id')
            ->first();

        return $delivery instanceof PopupAnnouncementDelivery ? $delivery : null;
    }

    public function activeDeliveryForUser(int $userId, int $deliveryId): ?PopupAnnouncementDelivery
    {
        $delivery = PopupAnnouncementDelivery::query()
            ->with('announcement')
            ->forUser($userId)
            ->whereKey($deliveryId)
            ->first();

        return $delivery instanceof PopupAnnouncementDelivery ? $delivery : null;
    }

    /**
     * @return array{
     *     code:?string,
     *     title:string,
     *     message:string,
     *     priority:?string,
     *     priority_label:string,
     *     priority_classes:string,
     *     require_ack:bool,
     *     mode_label:string,
     *     intro_text:string,
     *     footer_text:string,
     *     can_dismiss:bool,
     *     primary_action_label:string,
     *     primary_action_method:string,
     *     primary_action_color:string,
     *     primary_action_target:string,
     *     dialog_id:string,
     *     meta_text:string,
     *     starts_at:?string,
     *     ends_at:?string
     * }|null
     */
    public function announcementPayload(PopupAnnouncementDelivery $delivery): ?array
    {
        $announcement = $delivery->announcement;

        if (! $announcement instanceof PopupAnnouncement) {
            return null;
        }

        $priority = $announcement->priority ?: PopupAnnouncement::PRIORITY_INFO;
        $requiresAck = (bool) $announcement->require_ack;

        return [
            'code' => $announcement->code,
            'title' => $announcement->title,
            'message' => $announcement->message,
            'priority' => $priority,
            'priority_label' => $this->priorityLabel($priority),
            'priority_classes' => $this->priorityClasses($priority),
            'require_ack' => $requiresAck,
            'mode_label' => $requiresAck ? 'Cần xác nhận' : 'Đọc & đóng',
            'intro_text' => $requiresAck
                ? 'Cần xác nhận đã đọc trước khi tiếp tục thao tác.'
                : 'Bạn có thể đóng popup khi đã nắm thông tin quan trọng này.',
            'footer_text' => $requiresAck
                ? 'Popup sẽ chỉ biến mất sau khi bạn xác nhận đã đọc.'
                : 'Popup này chỉ hiển thị một lần để tránh gây nhiễu thao tác.',
            'can_dismiss' => ! $requiresAck,
            'primary_action_label' => $requiresAck ? 'Tôi đã đọc' : 'Đóng thông báo',
            'primary_action_method' => $requiresAck ? 'acknowledge' : 'dismiss',
            'primary_action_color' => $requiresAck ? 'primary' : 'gray',
            'primary_action_target' => $requiresAck ? 'acknowledge' : 'dismiss',
            'dialog_id' => 'popup-announcement-'.$delivery->id,
            'meta_text' => $this->metaText(
                code: $announcement->code,
                startsAt: optional($announcement->starts_at)?->format('d/m/Y H:i'),
                endsAt: optional($announcement->ends_at)?->format('d/m/Y H:i'),
            ),
            'starts_at' => optional($announcement->starts_at)?->format('d/m/Y H:i'),
            'ends_at' => optional($announcement->ends_at)?->format('d/m/Y H:i'),
        ];
    }

    protected function priorityLabel(string $priority): string
    {
        return match ($priority) {
            PopupAnnouncement::PRIORITY_SUCCESS => 'Thành công',
            PopupAnnouncement::PRIORITY_WARNING => 'Cảnh báo',
            PopupAnnouncement::PRIORITY_DANGER => 'Khẩn cấp',
            default => 'Thông tin',
        };
    }

    protected function priorityClasses(string $priority): string
    {
        return match ($priority) {
            PopupAnnouncement::PRIORITY_SUCCESS => 'border-emerald-200 bg-emerald-50 text-emerald-700',
            PopupAnnouncement::PRIORITY_WARNING => 'border-amber-200 bg-amber-50 text-amber-700',
            PopupAnnouncement::PRIORITY_DANGER => 'border-rose-200 bg-rose-50 text-rose-700',
            default => 'border-blue-200 bg-blue-50 text-blue-700',
        };
    }

    protected function metaText(?string $code, ?string $startsAt, ?string $endsAt): string
    {
        $segments = ['Mã: '.($code ?: '-')];

        if (filled($startsAt)) {
            $segments[] = 'Bắt đầu: '.$startsAt;
        }

        if (filled($endsAt)) {
            $segments[] = 'Kết thúc: '.$endsAt;
        }

        return implode(' · ', $segments);
    }
}
