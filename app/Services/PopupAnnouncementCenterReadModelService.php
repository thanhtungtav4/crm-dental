<?php

namespace App\Services;

use App\Models\PopupAnnouncement;
use App\Models\PopupAnnouncementDelivery;

/**
 * @phpstan-type PopupAnnouncementCenterAction array{
 *     label:string,
 *     wire_click:string,
 *     color:string,
 *     wire_target:string
 * }
 * @phpstan-type PopupAnnouncementCenterCloseAction array{
 *     label:string,
 *     wire_click:string,
 *     wire_target:string
 * }
 * @phpstan-type PopupAnnouncementCenterPayload array{
 *     code:?string,
 *     title:string,
 *     message:string,
 *     priority:?string,
 *     priority_label:string,
 *     priority_classes:string,
 *     require_ack:bool,
 *     mode_label:string,
 *     mode_classes:string,
 *     intro_text:string,
 *     footer_text:string,
 *     can_dismiss:bool,
 *     primary_action:PopupAnnouncementCenterAction,
 *     close_action:?PopupAnnouncementCenterCloseAction,
 *     dialog_id:string,
 *     dialog_aria_describedby:string,
 *     title_id:string,
 *     meta_id:string,
 *     body_id:string,
 *     meta_text:string,
 *     starts_at:?string,
 *     ends_at:?string
 * }
 * @phpstan-type PopupAnnouncementCenterViewState array{
 *     announcement:?PopupAnnouncementCenterPayload,
 *     has_announcement:bool,
 *     polling_interval:int,
 *     aria_live:string
 * }
 */
class PopupAnnouncementCenterReadModelService
{
    /** @return PopupAnnouncementCenterViewState */
    public function centerViewState(?array $announcement, int $pollingInterval): array
    {
        return [
            'announcement' => $announcement,
            'has_announcement' => $announcement !== null,
            'polling_interval' => max(5, min(60, $pollingInterval)),
            'aria_live' => 'polite',
        ];
    }

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

    /** @return PopupAnnouncementCenterPayload|null */
    public function announcementPayload(PopupAnnouncementDelivery $delivery): ?array
    {
        $announcement = $delivery->announcement;

        if (! $announcement instanceof PopupAnnouncement) {
            return null;
        }

        $priority = $announcement->priority ?: PopupAnnouncement::PRIORITY_INFO;
        $requiresAck = (bool) $announcement->require_ack;
        $dialogId = 'popup-announcement-'.$delivery->id;
        $startsAt = optional($announcement->starts_at)?->format('d/m/Y H:i');
        $endsAt = optional($announcement->ends_at)?->format('d/m/Y H:i');
        $dialogAttributes = $this->dialogAttributes($dialogId);

        return [
            'code' => $announcement->code,
            'title' => $announcement->title,
            'message' => $announcement->message,
            'priority' => $priority,
            'priority_label' => $this->priorityLabel($priority),
            'priority_classes' => $this->priorityClasses($priority),
            'require_ack' => $requiresAck,
            'mode_label' => $requiresAck ? 'Cần xác nhận' : 'Đọc & đóng',
            'mode_classes' => $this->modeClasses($requiresAck),
            'intro_text' => $requiresAck
                ? 'Cần xác nhận đã đọc trước khi tiếp tục thao tác.'
                : 'Bạn có thể đóng popup khi đã nắm thông tin quan trọng này.',
            'footer_text' => $requiresAck
                ? 'Popup sẽ chỉ biến mất sau khi bạn xác nhận đã đọc.'
                : 'Popup này chỉ hiển thị một lần để tránh gây nhiễu thao tác.',
            'can_dismiss' => ! $requiresAck,
            'primary_action' => $this->primaryActionPayload($requiresAck),
            'close_action' => $this->closeActionPayload($requiresAck),
            'dialog_id' => $dialogAttributes['dialog_id'],
            'dialog_aria_describedby' => $dialogAttributes['aria_describedby'],
            'title_id' => $dialogAttributes['title_id'],
            'meta_id' => $dialogAttributes['meta_id'],
            'body_id' => $dialogAttributes['body_id'],
            'meta_text' => $this->metaText(
                code: $announcement->code,
                startsAt: $startsAt,
                endsAt: $endsAt,
            ),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ];
    }

    /** @return PopupAnnouncementCenterAction */
    protected function primaryActionPayload(bool $requiresAck): array
    {
        return [
            'label' => $requiresAck ? 'Tôi đã đọc' : 'Đóng thông báo',
            'wire_click' => $requiresAck ? 'acknowledge' : 'dismiss',
            'color' => $requiresAck ? 'primary' : 'gray',
            'wire_target' => $requiresAck ? 'acknowledge' : 'dismiss',
        ];
    }

    /** @return PopupAnnouncementCenterCloseAction|null */
    protected function closeActionPayload(bool $requiresAck): ?array
    {
        if ($requiresAck) {
            return null;
        }

        return [
            'label' => 'Đóng thông báo',
            'wire_click' => 'dismiss',
            'wire_target' => 'dismiss',
        ];
    }

    /**
     * @return array{
     *     dialog_id:string,
     *     aria_describedby:string,
     *     title_id:string,
     *     meta_id:string,
     *     body_id:string
     * }
     */
    protected function dialogAttributes(string $dialogId): array
    {
        return [
            'dialog_id' => $dialogId,
            'aria_describedby' => $dialogId.'-meta '.$dialogId.'-body',
            'title_id' => $dialogId.'-title',
            'meta_id' => $dialogId.'-meta',
            'body_id' => $dialogId.'-body',
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

    protected function modeClasses(bool $requiresAck): string
    {
        if ($requiresAck) {
            return 'border-primary-200 bg-primary-50 text-primary-700 dark:border-primary-500/30 dark:bg-primary-500/10 dark:text-primary-200';
        }

        return 'border-slate-200 bg-slate-50 text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300';
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
