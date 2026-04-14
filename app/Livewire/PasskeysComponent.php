<?php

namespace App\Livewire;

use Illuminate\View\View;
use Jeffgreco13\FilamentBreezy\Livewire\MyProfileComponent;

class PasskeysComponent extends MyProfileComponent
{
    protected string $view = 'livewire.passkeys-component';

    public static $sort = 50;

    /**
     * @return array{
     *     heading:string,
     *     description:string,
     *     unsupported_panel:array{
     *         title:string,
     *         insecure_context_message:string,
     *         unsupported_api_message:string,
     *         requirements:list<string>
     *     },
     *     checking_label:string,
     * }
     */
    public function viewState(): array
    {
        return [
            'heading' => 'Khóa truy cập (Passkey)',
            'description' => 'Khóa truy cập (passkey) cho phép bạn đăng nhập mà không cần mật khẩu. Thay vì mật khẩu, bạn có thể tạo passkey sẽ được lưu trữ trong 1Password, ứng dụng mật khẩu của MacOS, hoặc các ứng dụng tương tự trên hệ điều hành của bạn.',
            'unsupported_panel' => [
                'title' => 'Không thể sử dụng Passkey trên môi trường hiện tại',
                'insecure_context_message' => 'Trình duyệt đang ở ngữ cảnh không bảo mật',
                'unsupported_api_message' => 'Trình duyệt chưa hỗ trợ đầy đủ WebAuthn/Passkeys.',
                'requirements' => [
                    'Sử dụng trình duyệt hiện đại (Chrome 109+, Safari 16+, Firefox 119+)',
                    'Truy cập qua HTTPS (ví dụ: https://crm.test) hoặc localhost',
                    'Đảm bảo trình duyệt được cập nhật lên phiên bản mới nhất',
                ],
            ],
            'checking_label' => 'Đang kiểm tra hỗ trợ WebAuthn...',
        ];
    }

    public function render(): View
    {
        return view($this->view, [
            'viewState' => $this->viewState(),
        ]);
    }
}
