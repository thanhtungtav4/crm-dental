# Bắt đầu nhanh với CRM Nha khoa

## 1) Đăng nhập

Bạn cần:

- email hoặc tài khoản được cấp
- mật khẩu
- với `Admin` và `Manager`: cần thêm bước MFA

Nếu bạn đang dùng môi trường demo local:

- xem tài khoản mẫu ở [LOCAL_DEMO_USERS.md](LOCAL_DEMO_USERS.md)
- khi seed local, terminal sẽ in recovery code MFA cho account nhạy cảm

## 2) Sau khi đăng nhập sẽ thấy gì

Thông thường bạn sẽ được đưa vào trang quản trị chính.

Tùy vai trò, bạn sẽ thấy các nhóm menu như:

- Khách hàng
- Bệnh nhân
- Lịch hẹn
- Chăm sóc khách hàng
- Khám và điều trị
- Hóa đơn / Thu chi
- Báo cáo
- Cài đặt

Không phải ai cũng thấy tất cả menu.

## 3) Quy tắc dùng hệ thống an toàn

- Luôn tìm hồ sơ cũ trước khi tạo mới.
- Chỉ chuyển `Khách hàng` thành `Bệnh nhân` khi chắc đó là đúng người.
- Nếu lịch hẹn còn ở tương lai, không được cập nhật thành `Hoàn thành` hoặc `Không đến`.
- Nếu đổi lịch, luôn nhập lý do.
- Nếu hủy lịch, luôn nhập lý do.
- Nếu thao tác liên quan tài chính, cần dùng đúng vai trò được cấp.

## 4) Khi nào sẽ gặp lỗi thường gặp

### Màn hình `403`

Nguyên nhân thường là:

- bạn không có quyền vào module đó
- bạn đang mở link do người khác gửi nhưng ngoài phạm vi vai trò của bạn

### Không thấy bệnh nhân / khách hàng / lịch hẹn

Nguyên nhân thường là:

- dữ liệu thuộc chi nhánh khác
- bạn đang lọc sai
- hồ sơ chưa được chuyển đổi hoặc chưa tạo thành công

### Bị yêu cầu xác thực hai yếu tố

Điều này là bình thường với vai trò nhạy cảm như:

- `Admin`
- `Manager`

## 5) Cách điều hướng nhanh

- Muốn tạo khách hàng mới: vào màn hình khách hàng
- Muốn đặt lịch cho khách mới: tạo khách hàng trước, sau đó đặt lịch
- Muốn mở hồ sơ bệnh nhân: vào danh sách bệnh nhân rồi chọn hồ sơ
- Muốn xem tài chính: dùng account quản lý hoặc admin

## 6) Nên đọc tiếp gì

- lễ tân / tư vấn / CSKH: [Hướng dẫn Lễ tân / CSKH](USER_GUIDE_FRONTDESK_CSKH.md)
- bác sĩ: [Hướng dẫn Bác sĩ](USER_GUIDE_DOCTOR.md)
- quản lý / admin: [Hướng dẫn Quản lý / Admin](USER_GUIDE_MANAGER_ADMIN.md)
