# Đánh giá rủi ro thao tác sai người dùng có thể ảnh hưởng hệ thống CRM

## Phạm vi đánh giá
- Tập trung vào các luồng có rủi ro cao: phân quyền theo chi nhánh, tài chính (invoice/payment), và kho vật tư.
- Đánh giá dựa trên mã nguồn hiện tại (policy, resource, model hooks, test bảo mật).

## Kết luận nhanh
- **Mức rủi ro tổng quan: Trung bình**.
- **Điểm mạnh:** module tài chính được harden khá tốt (chặn sửa/xóa phiếu thu, lock transaction, anti-overpay theo policy runtime, audit log).
- **Điểm yếu cần ưu tiên xử lý:** luồng vật tư điều trị (`TreatmentMaterial`) còn thiếu guard quan trọng, có thể dẫn tới lệch kho hoặc truy cập chéo chi nhánh nếu người dùng thao tác sai hoặc cố tình nhập dữ liệu bất thường.

## Điểm mạnh đang giảm rủi ro thao tác sai

### 1) Luồng thanh toán có cơ chế chống sai sót nghiệp vụ tốt
- Payment bị chặn cập nhật/xóa trực tiếp ở tầng model; buộc xử lý bằng phiếu hoàn/đảo phiếu.
- Refund/reversal bị ràng buộc quyền nhạy cảm (`ActionPermission::PAYMENT_REVERSAL`).
- Tạo payment dùng transaction + `lockForUpdate()` + kiểm soát overpay (nếu policy cấu hình không cho phép).

### 2) Có registry cho hành động nhạy cảm + test đồng bộ quyền
- `SensitiveActionRegistry` xác định role nào được phép với từng action nhạy cảm.
- Có test tự động kiểm tra marker anti-bypass và role matrix cho toàn bộ action nhạy cảm.

### 3) Tách dữ liệu theo chi nhánh đã có ở nhiều module lõi
- User có hàm `canAccessBranch`, `accessibleBranchIds`, `hasAnyAccessibleBranch`.
- Các resource như Patients/Payments có query scope theo branch để tránh nhìn thấy dữ liệu ngoài phạm vi.

## Rủi ro chính do thao tác người dùng (ưu tiên cao)

### R1) Vật tư điều trị có thể bị ghi nhận số lượng bất thường làm lệch kho
- Form `TreatmentMaterial` chỉ `numeric()` nhưng chưa ràng buộc `minValue(1)`.
- Observer `TreatmentMaterial` trừ kho bằng `decrement('stock_qty', quantity)` mà không validate số lượng dương hoặc không vượt tồn.
- Nếu nhập số âm hoặc số rất lớn, có thể gây tăng/giảm kho sai nghiêm trọng.

**Tác động:** Sai lệch tồn kho, sai báo cáo tài chính - vật tư, ảnh hưởng kế hoạch nhập hàng.

### R2) Thiếu kiểm soát truy cập theo chi nhánh cho `TreatmentMaterial`
- `TreatmentMaterialPolicy` chỉ kiểm tra permission dạng `View/Create/Update...` nhưng không check branch.
- `TreatmentMaterialResource` chưa override `getEloquentQuery()` để lọc theo branch người dùng.

**Tác động:** Người dùng có quyền nghiệp vụ nhưng ở chi nhánh A có thể xem/sửa bản ghi liên quan chi nhánh B (nếu được cấp permission tương ứng).

## Rủi ro trung bình

### R3) Rủi ro vận hành khi cấp quyền sai role
- Hệ thống có matrix role-action khá tốt, nhưng nếu cấp role “quá rộng” ở môi trường thực tế thì thao tác sai người dùng vẫn có thể gây ảnh hưởng lớn (đảo phiếu, đồng bộ master data, chuyển bệnh nhân liên chi nhánh).

**Tác động:** Thay đổi dữ liệu diện rộng, khó rollback nếu quy trình kiểm duyệt nội bộ lỏng.

## Khuyến nghị ưu tiên (theo thứ tự triển khai)

1. **Khóa input số lượng vật tư ở cả UI + model/database**
   - Thêm `minValue(1)` ở form.
   - Validate tại model/service trước khi ghi (không tin tưởng UI).
   - Thêm DB check constraint (nếu DB hỗ trợ) để chặn `quantity <= 0`.

2. **Bổ sung branch guard cho TreatmentMaterial**
   - Áp dụng pattern như `PaymentPolicy` / `PatientPolicy`: check `canAccessBranch(...)` theo nhánh của session/material.
   - Thêm query scope branch trong `TreatmentMaterialResource::getEloquentQuery()`.

3. **Thêm test regression cho 2 lỗ hổng trên**
   - Test từ chối quantity âm/0.
   - Test user chi nhánh A không truy cập được treatment material của chi nhánh B.

4. **Thiết lập cảnh báo vận hành**
   - Alert khi `stock_qty` xuống âm hoặc biến động vượt ngưỡng/ngày.
   - Dashboard “anomaly” cho giao dịch vật tư và payment reversal.

## Kết luận
- CRM hiện tại đã có nền tảng kiểm soát tốt ở mảng tài chính và hành động nhạy cảm.
- Tuy nhiên, để giảm nguy cơ “người dùng thao tác sai ảnh hưởng hệ thống”, cần xử lý sớm lỗ hổng ở module vật tư điều trị (validation + phân quyền chi nhánh + test). Đây là điểm rủi ro thực tế cao nhất trong code hiện tại.
