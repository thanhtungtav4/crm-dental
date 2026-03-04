<?php

return [
    'login' => [
        'username_or_email' => 'Tên tài khoản hoặc email',
        'forgot_password_link' => 'Quên mật khẩu?',
        'create_an_account' => 'Tạo tài khoản mới',
    ],
    'password_confirm' => [
        'heading' => 'Xác nhận mật khẩu',
        'description' => 'Vui lòng xác nhận mật khẩu của bạn để hoàn tất thao tác này.',
        'current_password' => 'Mật khẩu hiện tại',
    ],
    'two_factor' => [
        'heading' => 'Xác thực hai yếu tố',
        'description' => 'Vui lòng xác nhận quyền truy cập vào tài khoản bằng mã từ ứng dụng xác thực.',
        'code_placeholder' => 'XXX-XXX',
        'recovery' => [
            'heading' => 'Xác thực hai yếu tố',
            'description' => 'Vui lòng xác nhận quyền truy cập bằng một mã khôi phục khẩn cấp.',
        ],
        'recovery_code_placeholder' => 'abcdef-98765',
        'recovery_code_text' => 'Mất thiết bị?',
        'recovery_code_link' => 'Dùng mã khôi phục',
        'back_to_login_link' => 'Quay lại đăng nhập',
    ],
    'registration' => [
        'title' => 'Đăng ký',
        'heading' => 'Tạo tài khoản mới',
        'submit' => [
            'label' => 'Đăng ký',
        ],
        'notification_unique' => 'Email này đã tồn tại, vui lòng đăng nhập.',
    ],
    'reset_password' => [
        'title' => 'Quên mật khẩu',
        'heading' => 'Đặt lại mật khẩu',
        'submit' => [
            'label' => 'Gửi',
        ],
        'notification_error' => 'Có lỗi xảy ra khi đặt lại mật khẩu. Vui lòng thử lại.',
        'notification_error_link_text' => 'Thử lại',
        'notification_success' => 'Vui lòng kiểm tra email để tiếp tục.',
    ],
    'verification' => [
        'title' => 'Xác minh email',
        'heading' => 'Yêu cầu xác minh email',
        'submit' => [
            'label' => 'Đăng xuất',
        ],
        'notification_success' => 'Vui lòng kiểm tra email để tiếp tục.',
        'notification_resend' => 'Đã gửi lại email xác minh.',
        'before_proceeding' => 'Trước khi tiếp tục, vui lòng kiểm tra email để lấy liên kết xác minh.',
        'not_receive' => 'Nếu bạn chưa nhận được email,',
        'request_another' => 'bấm vào đây để yêu cầu email khác',
    ],
    'profile' => [
        'account' => 'Tài khoản',
        'profile' => 'Hồ sơ',
        'my_profile' => 'Hồ sơ của tôi',
        'subheading' => 'Quản lý hồ sơ người dùng tại đây.',
        'mfa_required_notice' => [
            'title' => 'Bắt buộc bật MFA trước khi tiếp tục',
            'body' => 'Bạn cần hoàn tất ít nhất một phương thức bảo mật: 2FA hoặc Passkey.',
            'description' => 'Tài khoản của bạn thuộc nhóm bắt buộc MFA, nên hệ thống đang tạm chặn truy cập các màn hình nghiệp vụ nhạy cảm.',
            'step_2fa' => 'Cách 1 (khuyến nghị): vào mục "Xác thực hai yếu tố", bấm "Bật", quét QR, nhập OTP rồi bấm "Xác nhận & hoàn tất".',
            'step_passkey' => 'Cách 2: vào mục "Khóa truy cập (Passkey)", tạo khóa mới và xác nhận thành công trên thiết bị.',
            'done' => 'Sau khi hoàn tất một trong hai cách, tải lại trang để tiếp tục làm việc.',
        ],
        'personal_info' => [
            'heading' => 'Thông tin cá nhân',
            'subheading' => 'Quản lý thông tin cá nhân của bạn.',
            'submit' => [
                'label' => 'Cập nhật',
            ],
            'notify' => 'Cập nhật hồ sơ thành công!',
        ],
        'password' => [
            'heading' => 'Mật khẩu',
            'subheading' => 'Mật khẩu phải có ít nhất 8 ký tự.',
            'submit' => [
                'label' => 'Cập nhật',
            ],
            'notify' => 'Cập nhật mật khẩu thành công!',
        ],
        '2fa' => [
            'title' => 'Xác thực hai yếu tố',
            'description' => 'Quản lý xác thực hai yếu tố cho tài khoản của bạn (khuyến nghị bật).',
            'actions' => [
                'enable' => 'Bật',
                'regenerate_codes' => 'Tạo lại mã khôi phục',
                'disable' => 'Tắt',
                'confirm_finish' => 'Xác nhận & hoàn tất',
                'cancel_setup' => 'Hủy thiết lập',
                'confirm' => 'Xác nhận',
            ],
            'setup_key' => 'Khóa thiết lập',
            'must_enable' => 'Bạn cần bật xác thực hai yếu tố để sử dụng ứng dụng này.',
            'not_enabled' => [
                'title' => 'Bạn chưa bật xác thực hai yếu tố.',
                'description' => 'Khi bật xác thực hai yếu tố, bạn sẽ được yêu cầu nhập mã bảo mật khi đăng nhập. Bạn có thể dùng Google Authenticator hoặc ứng dụng tương tự.',
            ],
            'finish_enabling' => [
                'title' => 'Hoàn tất bật xác thực hai yếu tố.',
                'description' => 'Để hoàn tất, hãy quét mã QR bằng ứng dụng xác thực hoặc nhập khóa thiết lập và mã OTP được tạo.',
            ],
            'enabled' => [
                'notify' => 'Đã bật xác thực hai yếu tố.',
                'title' => 'Bạn đã bật xác thực hai yếu tố!',
                'description' => 'Xác thực hai yếu tố đã được kích hoạt, giúp tài khoản an toàn hơn.',
                'store_codes' => 'Lưu trữ các mã khôi phục này ở nơi an toàn. Các mã này giúp bạn lấy lại quyền truy cập khi mất thiết bị.',
                'show_codes' => 'Hiển thị mã khôi phục',
                'hide_codes' => 'Ẩn mã khôi phục',
            ],
            'disabling' => [
                'notify' => 'Đã tắt xác thực hai yếu tố.',
            ],
            'regenerate_codes' => [
                'notify' => 'Đã tạo mã khôi phục mới.',
            ],
            'confirmation' => [
                'success_notification' => 'Xác minh mã thành công. Đã bật xác thực hai yếu tố.',
                'invalid_code' => 'Mã bạn nhập không hợp lệ.',
            ],
        ],
        'sanctum' => [
            'title' => 'Token API',
            'description' => 'Quản lý token API cho phép dịch vụ bên thứ ba truy cập ứng dụng thay bạn.',
            'create' => [
                'notify' => 'Tạo token thành công!',
                'message' => 'Token chỉ hiển thị một lần khi tạo. Nếu mất token, bạn cần xóa và tạo token mới.',
                'submit' => [
                    'label' => 'Tạo',
                ],
            ],
            'update' => [
                'notify' => 'Cập nhật token thành công!',
                'submit' => [
                    'label' => 'Cập nhật',
                ],
            ],
            'copied' => [
                'label' => 'Tôi đã sao chép token',
            ],
        ],
        'browser_sessions' => [
            'heading' => 'Phiên trình duyệt',
            'subheading' => 'Quản lý các phiên đăng nhập đang hoạt động của bạn.',
            'label' => 'Phiên trình duyệt',
            'content' => 'Bạn có thể đăng xuất tất cả phiên trình duyệt khác trên mọi thiết bị. Danh sách dưới đây có thể không đầy đủ. Nếu nghi ngờ tài khoản bị lộ, hãy đổi mật khẩu ngay.',
            'device' => 'Thiết bị này',
            'last_active' => 'Hoạt động lần cuối',
            'logout_other_sessions' => 'Đăng xuất các phiên khác',
            'logout_heading' => 'Đăng xuất các phiên trình duyệt khác',
            'logout_description' => 'Vui lòng nhập mật khẩu để xác nhận đăng xuất toàn bộ phiên khác trên các thiết bị.',
            'logout_action' => 'Đăng xuất các phiên khác',
            'incorrect_password' => 'Mật khẩu bạn nhập không chính xác. Vui lòng thử lại.',
            'logout_success' => 'Đã đăng xuất thành công tất cả phiên trình duyệt khác.',
        ],
    ],
    'clipboard' => [
        'link' => 'Sao chép',
        'tooltip' => 'Đã sao chép!',
    ],
    'fields' => [
        'avatar' => 'Ảnh đại diện',
        'email' => 'Email',
        'login' => 'Đăng nhập',
        'name' => 'Tên',
        'password' => 'Mật khẩu',
        'password_confirm' => 'Xác nhận mật khẩu',
        'new_password' => 'Mật khẩu mới',
        'new_password_confirmation' => 'Xác nhận mật khẩu mới',
        'token_name' => 'Tên token',
        'token_expiry' => 'Hạn token',
        'abilities' => 'Quyền',
        '2fa_code' => 'Mã',
        '2fa_recovery_code' => 'Mã khôi phục',
        'created' => 'Ngày tạo',
        'expires' => 'Hết hạn',
    ],
    'or' => 'Hoặc',
    'cancel' => 'Hủy',
];

