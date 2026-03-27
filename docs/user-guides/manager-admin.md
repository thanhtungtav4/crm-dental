# Hướng dẫn Quản lý / Admin

Tài liệu này dành cho người dùng quản lý chi nhánh và quản trị hệ thống.

## Phân biệt nhanh

### Manager

Thường dùng để:

- xem dashboard tài chính theo chi nhánh
- theo dõi thu/chi trong phạm vi chi nhánh
- giám sát vận hành lịch hẹn, bệnh nhân, CSKH

Manager thường không được vào:

- firewall IP
- một số surface hạ tầng hoặc hệ thống toàn cục

### Admin

Thường dùng để:

- cấu hình hệ thống
- xem audit / governance surface
- xử lý quyền và vận hành hệ thống
- vào các màn hình admin-only như firewall

## 1) MFA là bắt buộc

Với `Manager` và `Admin`, sau bước nhập mật khẩu sẽ có bước MFA.

Điều này là bình thường và không nên tắt chỉ để thao tác nhanh hơn.

## 2) Dashboard và tài chính

Manager nên dùng các màn hình:

- Dashboard tài chính
- Thu/chi
- công nợ, overdue, báo cáo theo chi nhánh

Quy tắc:

- dữ liệu phải bám branch scope
- không kỳ vọng nhìn thấy dữ liệu ngoài phạm vi chi nhánh nếu đang dùng role manager

## 3) Vận hành đội ngũ

Quản lý nên kiểm tra định kỳ:

- lịch hẹn sắp tới và no-show
- care queue
- hóa đơn overdue
- tiến độ follow-up

Nếu thấy dữ liệu bất thường:

- kiểm tra chi nhánh lọc hiện tại
- kiểm tra xem user có đang dùng đúng vai trò không

## 4) Surface chỉ admin nên dùng

Các màn hình nhạy cảm như:

- firewall IP
- một số cài đặt hệ thống
- các surface governance / audit sâu

chỉ nên dùng bằng account admin.

## 5) Điều cần tránh

- không dùng tài khoản admin cho công việc lễ tân hằng ngày
- không chia sẻ tài khoản MFA cho nhiều người
- không sửa trực tiếp dữ liệu nhạy cảm nếu đã có workflow chuẩn

## 6) Khi cần xem tài liệu khác

- thao tác hàng ngày cho user thường: [Bắt đầu nhanh](getting-started.md)
- luồng khách hàng và lịch hẹn: [Lễ tân / CSKH](frontdesk-cskh.md)
- luồng khám điều trị: [Bác sĩ](doctor.md)
