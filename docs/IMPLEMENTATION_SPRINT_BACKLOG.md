# Implementation Backlog Theo Sprint (Ticket-Ready)

> Dự án: CRM Nha khoa (Laravel + Filament)  
> Cập nhật: 2026-02-24  
> Mục tiêu: đóng các gap nghiệp vụ/công nghệ quan trọng và đưa flow về mức production-ready theo tham chiếu DentalFlow.

---

## 1) Khung triển khai

- Chu kỳ sprint: 2 tuần/sprint
- Tổng roadmap: 4 sprint (8 tuần)
- Team giả định: `BE x2`, `FE x2`, `QA x2`, `PM x1`
- Độ ưu tiên: `P0` (critical), `P1` (high), `P2` (medium)

### Definition of Done (áp dụng cho mọi ticket)

1. Có code + migration (nếu cần) + test tương ứng.
2. Không phá luồng cũ đã ổn định (regression pass).
3. Có checklist QA pass trên staging.
4. Có log/monitoring cơ bản cho luồng mới (nếu là flow nghiệp vụ trọng yếu).
5. Có cập nhật docs ngắn trong PR hoặc `docs/`.

---

## 2) Sprint 1 - Data Identity + Lead/Patient Governance

### Mục tiêu sprint

- Chuẩn hóa định danh bệnh nhân.
- Hợp nhất toàn bộ luồng chuyển Lead -> Patient về 1 service.
- Chặn tạo trùng hồ sơ ở tầng DB + business rule.

### Exit criteria

1. Chỉ còn duy nhất 1 conversion pipeline.
2. Không thể tạo 2 patient cho cùng 1 customer.
3. Patient code dùng 1 chuẩn thống nhất toàn hệ thống.

---

### TICKET S1-01 (P0)
- **Title**: Enforce ràng buộc 1-1 giữa `customers` và `patients`
- **Type**: Story (BE + DB)
- **Estimate**: 5 SP
- **Dependencies**: Không
- **Scope**:
  - Thêm unique constraint phù hợp cho `patients.customer_id` (có xử lý dữ liệu cũ vi phạm).
  - Viết script pre-check để liệt kê duplicate trước khi migrate.
- **Acceptance Criteria (QA)**:
  1. Given DB có 2 patient cùng `customer_id`, when chạy pre-check, then trả danh sách conflict rõ ràng.
  2. Given DB sạch, when chạy migration, then unique constraint được tạo thành công.
  3. Given hệ thống đang chạy, when cố tạo patient thứ 2 cho cùng customer, then thao tác bị chặn và có thông báo nghiệp vụ dễ hiểu.

### TICKET S1-02 (P0)
- **Title**: Hợp nhất conversion Lead -> Patient về `PatientConversionService`
- **Type**: Story (BE)
- **Estimate**: 8 SP
- **Dependencies**: S1-01
- **Scope**:
  - Tất cả entry-point conversion (Customers table, Appointments table, model helper) phải gọi chung service.
  - Loại bỏ logic tạo patient trực tiếp rải rác.
- **Acceptance Criteria (QA)**:
  1. Given user convert từ màn Khách hàng, when submit, then dùng chung rule với màn Lịch hẹn.
  2. Given user convert từ màn Lịch hẹn, when submit, then không tạo path xử lý riêng.
  3. Given lỗi validate conversion, when xảy ra lỗi, then thông báo nhất quán giữa các màn.

### TICKET S1-03 (P0)
- **Title**: Chuẩn hóa chiến lược sinh `patient_code`
- **Type**: Story (BE)
- **Estimate**: 5 SP
- **Dependencies**: S1-02
- **Scope**:
  - Chọn 1 chuẩn duy nhất (khuyến nghị: `PAT-YYYYMMDD-XXXXXX`).
  - Backfill dữ liệu cũ nếu đang trộn `BNxxxxxx`.
- **Acceptance Criteria (QA)**:
  1. Given tạo patient mới từ mọi luồng, when lưu thành công, then mã hồ sơ theo đúng 1 format.
  2. Given record cũ đã backfill, when mở danh sách, then không còn lẫn 2 chuẩn mã.
  3. Given concurrent create, when tạo nhiều hồ sơ gần đồng thời, then không phát sinh mã trùng.

### TICKET S1-04 (P1)
- **Title**: Chống trùng bệnh nhân theo policy `phone + clinic`
- **Type**: Story (BE + FE)
- **Estimate**: 8 SP
- **Dependencies**: S1-01, S1-02
- **Scope**:
  - Chuẩn hóa rule dedupe trước conversion.
  - Nếu trùng thì hiển thị “link existing patient” thay vì tạo mới.
- **Acceptance Criteria (QA)**:
  1. Given lead có phone đã tồn tại cùng clinic, when convert, then hệ thống đề xuất liên kết hồ sơ có sẵn.
  2. Given lead có phone trùng clinic khác (nếu policy cho phép), when convert, then xử lý đúng theo cấu hình đã chọn.
  3. Given phone mới, when convert, then tạo hồ sơ mới bình thường.

### TICKET S1-05 (P1)
- **Title**: Đồng bộ hành vi xóa bệnh nhân (UI vs model rule)
- **Type**: Bugfix (BE + FE)
- **Estimate**: 3 SP
- **Dependencies**: Không
- **Scope**:
  - Nếu policy không cho xóa, ẩn nút xóa và thay bằng trạng thái “ngừng theo dõi/lưu trữ”.
- **Acceptance Criteria (QA)**:
  1. Given trang hồ sơ bệnh nhân, when mở header actions, then không thấy hành động trái với policy.
  2. Given người dùng cố gọi endpoint xóa trực tiếp, when request gửi lên, then vẫn bị chặn đúng rule.

### TICKET S1-06 (P1)
- **Title**: Bộ test regression cho conversion & identity
- **Type**: Task (BE + QA)
- **Estimate**: 5 SP
- **Dependencies**: S1-02, S1-03, S1-04
- **Scope**:
  - Feature test cho conversion từ từng entry-point.
  - Test duplicate + code generation + race conditions cơ bản.
- **Acceptance Criteria (QA)**:
  1. Given CI chạy test, when pipeline hoàn tất, then tất cả test identity pass.
  2. Given bug tái phát ở conversion, when chạy test suite, then test fail đúng ca.

---

## 3) Sprint 2 - Financial State Machine + Report Stability

### Mục tiêu sprint

- Chuẩn hóa trạng thái hóa đơn/thanh toán theo state machine rõ ràng.
- Chặn sai lệch giữa UI, filter, báo cáo.
- Loại lỗi “thống kê vỡ do thiếu bảng” ở môi trường vận hành.

### Exit criteria

1. `Invoice` có lifecycle nhất quán và persist đầy đủ (`draft/issued/partial/paid/overdue/cancelled`).
2. Báo cáo tài chính không crash khi môi trường thiếu thành phần.
3. Tab Thanh toán đồng bộ số liệu với báo cáo.

---

### TICKET S2-01 (P0)
- **Title**: Chuẩn hóa `Invoice` state machine và persist `overdue`
- **Type**: Story (BE)
- **Estimate**: 8 SP
- **Dependencies**: Không
- **Scope**:
  - Viết transition rules rõ ràng.
  - `overdue` là trạng thái thật (không chỉ suy diễn UI).
- **Acceptance Criteria (QA)**:
  1. Given hóa đơn quá hạn chưa thu đủ, when đến hạn, then status cập nhật `overdue`.
  2. Given hóa đơn overdue được thu đủ, when thanh toán đủ, then status chuyển `paid`.
  3. Given hóa đơn cancelled, when chạy job cập nhật overdue, then không đổi status.

### TICKET S2-02 (P1)
- **Title**: Chuẩn hóa luồng phiếu thu/hoàn và idempotent update số dư
- **Type**: Story (BE)
- **Estimate**: 8 SP
- **Dependencies**: S2-01
- **Scope**:
  - Đảm bảo receipt/refund cập nhật chính xác paid/balance.
  - Chống double-apply khi retry request.
- **Acceptance Criteria (QA)**:
  1. Given gửi cùng transaction_ref 2 lần, when xử lý, then chỉ ghi nhận 1 lần.
  2. Given có refund, when xem tab thanh toán, then số đã thu/net đúng theo nghiệp vụ.
  3. Given nhiều payment trên 1 invoice, when tính tổng, then khớp report.

### TICKET S2-03 (P1)
- **Title**: Hardening module `ReceiptsExpense` và fallback báo cáo
- **Type**: Bugfix (BE)
- **Estimate**: 5 SP
- **Dependencies**: Không
- **Scope**:
  - Guard resource/report nếu bảng chưa tồn tại.
  - Thông báo hướng dẫn migration rõ ràng thay vì lỗi SQL runtime.
- **Acceptance Criteria (QA)**:
  1. Given môi trường thiếu bảng `receipts_expense`, when mở module liên quan, then không 500.
  2. Given môi trường đủ migration, when mở module, then data hiển thị bình thường.
  3. Given người dùng chưa có quyền module, when truy cập, then nhận response theo policy phân quyền.

### TICKET S2-04 (P1)
- **Title**: Đồng bộ KPI tab Thanh toán với báo cáo tài chính
- **Type**: Story (BE + FE)
- **Estimate**: 5 SP
- **Dependencies**: S2-01, S2-02
- **Scope**:
  - Chuẩn hóa công thức: tổng điều trị, giảm giá, phải thu, đã thu, còn lại, số dư.
- **Acceptance Criteria (QA)**:
  1. Given dataset mẫu, when đối chiếu tab Thanh toán và báo cáo, then số khớp 100%.
  2. Given invoice overdue/partial/paid, when chuyển tab, then KPI cập nhật đúng.

### TICKET S2-05 (P2)
- **Title**: E2E QA pack cho flow thanh toán
- **Type**: QA Task
- **Estimate**: 5 SP
- **Dependencies**: S2-01..S2-04
- **Scope**:
  - Test matrix cho invoice lifecycle + receipt/refund + report.
- **Acceptance Criteria (QA)**:
  1. Có test case + evidence screenshot cho tối thiểu 15 scenario.
  2. Không còn blocker P0/P1 trước khi close sprint.

---

## 4) Sprint 3 - CSKH Ledger-First + Automation Workflow

### Mục tiêu sprint

- Chuyển page CSKH sang mô hình “ticket ledger first”.
- Tất cả trigger nghiệp vụ (no-show, đơn thuốc, sau điều trị, sinh nhật) đổ về một nguồn chuẩn.
- Tăng khả năng đo SLA và báo cáo CSKH.

### Exit criteria

1. CSKH page không query trực tiếp nhiều source rời rạc để biểu diễn nghiệp vụ chính.
2. Ticket có trạng thái/vòng đời rõ và theo dõi được SLA.
3. Job sinh nhật idempotent, timezone-safe.

---

### TICKET S3-01 (P0)
- **Title**: Chuẩn hóa domain “care ticket” làm nguồn dữ liệu trung tâm CSKH
- **Type**: Story (BE)
- **Estimate**: 8 SP
- **Dependencies**: Không
- **Scope**:
  - Chuẩn hóa schema ticket (dùng `notes` hiện tại hoặc tách bảng mới, nhưng phải thống nhất 1 nguồn đọc chính).
  - Mapping chuẩn `care_type`, `care_status`, `care_channel`, `source_type`, `source_id`.
- **Acceptance Criteria (QA)**:
  1. Given các sự kiện CSKH từ nhiều module, when ghi nhận, then mọi bản ghi vào cùng 1 ledger chuẩn.
  2. Given mở tab CSKH, when lọc theo loại/channels/status, then kết quả đúng và không trùng.

### TICKET S3-02 (P1)
- **Title**: Refactor `CustomerCare` page chỉ đọc từ care ledger
- **Type**: Story (BE + FE)
- **Estimate**: 8 SP
- **Dependencies**: S3-01
- **Scope**:
  - Loại bỏ query trực tiếp rời rạc từ appointments/prescriptions/sessions trong view bảng chính.
  - Chuẩn export CSV theo ledger.
- **Acceptance Criteria (QA)**:
  1. Given tạo ticket từ appointment/prescription/session, when vào CSKH, then dữ liệu xuất hiện ở đúng tab.
  2. Given export CSV theo tab, when tải file, then cột dữ liệu đúng chuẩn nghiệp vụ.

### TICKET S3-03 (P1)
- **Title**: Event mapping + observer sync ticket đầy đủ vòng đời
- **Type**: Story (BE)
- **Estimate**: 5 SP
- **Dependencies**: S3-01
- **Scope**:
  - Observer update/cancel ticket đúng khi source thay đổi trạng thái hoặc bị xóa.
  - Không tạo trùng ticket cho cùng source và care_type.
- **Acceptance Criteria (QA)**:
  1. Given appointment đổi `no_show` -> `completed`, when sync, then ticket reminder được cập nhật/hủy đúng.
  2. Given xóa source record, when sync, then ticket chuyển cancelled đúng rule.

### TICKET S3-04 (P1)
- **Title**: SLA & thao tác CSKH (planned/in_progress/completed/cancelled)
- **Type**: Story (BE + FE)
- **Estimate**: 5 SP
- **Dependencies**: S3-01, S3-02
- **Scope**:
  - Bổ sung action cập nhật trạng thái ticket và mốc thời gian xử lý.
  - Hiển thị badge SLA rõ ràng.
- **Acceptance Criteria (QA)**:
  1. Given ticket planned, when nhân viên bắt đầu xử lý, then chuyển `in_progress`.
  2. Given xử lý xong, when xác nhận, then chuyển `completed` và lưu timestamp.
  3. Given ticket quá hạn SLA, when xem danh sách, then hiển thị cảnh báo đúng.

### TICKET S3-05 (P2)
- **Title**: Hardening job sinh nhật CSKH (idempotent + timezone)
- **Type**: Task (BE)
- **Estimate**: 3 SP
- **Dependencies**: S3-01
- **Scope**:
  - Đảm bảo chạy lặp không tạo trùng theo năm.
  - Chuẩn timezone phòng khám.
- **Acceptance Criteria (QA)**:
  1. Given chạy command 2 lần cùng ngày, when kiểm tra DB, then không có ticket sinh nhật trùng.
  2. Given khác timezone cấu hình, when chạy lịch, then ngày sinh nhật được nhận diện đúng.

---

## 5) Sprint 4 - UI Refactor + Pixel-Level 1:1 + Regression Hardening

### Mục tiêu sprint

- Gỡ business logic khỏi Blade trang hồ sơ.
- Chuẩn hóa “1 style tổng thể” triệt để cho các tab: Lịch hẹn, Đơn thuốc, Thư viện ảnh, Thanh toán.
- Chốt visual parity + responsive + regression.

### Exit criteria

1. Không còn query nặng trong blade chính của patient workspace.
2. Inline style được loại bỏ/giảm tối đa, dùng token/style system chung.
3. Tab lõi đạt pixel-level parity với mẫu tham chiếu trong checklist QA.

### Trạng thái triển khai hiện tại

- [x] `S4-01` đã triển khai: query/aggregate được chuyển khỏi `view-patient.blade.php` sang page class.
- [x] `S4-02` đã chuẩn hóa token/style chính cho patient workspace (core tabs + block phụ trọng yếu).
- [x] `S4-03` đã chốt final QA parity 1:1 cho 4 tab lõi ở viewport desktop (`1536x864`) theo report định lượng.
- [x] `S4-04` đã hoàn tất kiểm thử responsive matrix `1366/1024/768` với bộ evidence đầy đủ.
- [x] `S4-05` đã có baseline visual regression + gate command (`composer qa:visual-regression`) cho 4 tab lõi desktop + modal/empty states chính.
- [x] `S4-06` đã cleanup artifact legacy và chuẩn hóa policy giữ/xóa evidence cho Playwright artifact.

Ghi nhận tiến độ gần nhất:
- `2026-02-25`: Đã chuẩn hóa thêm design token cho `crm-rel-tab`, `crm-care-tab`, `crm-payment-tab`, `crm-treatment-table` và đồng bộ màu/border/action về biến CSS dùng chung.
- `2026-02-25`: Bổ sung tinh chỉnh responsive cho mốc `1366px` và tăng độ ổn định horizontal overflow ở `1024px/768px` cho table + toolbar patient workspace.
- `2026-02-25`: Đã chạy bộ responsive evidence cho `1366/1024/768` trên 4 tab lõi + `basic-info` (15 ảnh) tại `output/playwright/`; có log tổng hợp tại `output/playwright/flow-check/2026-02-25-responsive-matrix.txt`.
- `2026-02-25`: Token hóa bổ sung các nhóm màu semantic cho hover/action/status (`crm-rel`, `crm-care`, `crm-payment`, `crm-treatment`) để giảm hardcode và đồng nhất style system.
- `2026-02-25`: Đã re-run responsive evidence sau đợt tokenization (bộ `-v2`, 15 ảnh) và ghi nhận không thấy regression thị giác rõ rệt; log tại `output/playwright/flow-check/2026-02-25-responsive-matrix-v2.txt`.
- `2026-02-25`: Đã đối chiếu parity desktop 1:1 với baseline cũ (`1536x864`) cho 4 tab lõi, kết quả PASS theo report định lượng tại `output/playwright/flow-check/2026-02-25-parity-desktop-v3.txt` (kèm diff overlay).
- `2026-02-25`: Tiếp tục token hóa block phụ trong patient workspace (`crm-feature-*`, `crm-link-list-*`, `crm-payment-summary`) để giảm thêm hardcode mà không đổi hành vi UI.
- `2026-02-25`: Chốt sign-off kỹ thuật cho `S4-03/S4-04` dựa trên 2 lớp bằng chứng:
  1. parity desktop: `output/playwright/flow-check/2026-02-25-parity-desktop-v3.txt`
  2. responsive matrix: `output/playwright/flow-check/2026-02-25-responsive-matrix-v2.txt`
- `2026-02-25`: Dọn artifact trung gian của vòng test trong ngày (loại bộ ảnh duplicate không hậu tố `-v2` và file smoke), giữ lại evidence chính cho audit/parity.
- `2026-02-25`: Chốt `S4-02` dựa trên kết quả tokenization đa vòng và kiểm tra không còn inline style trong patient blade/livewire views; biên bản tại `output/playwright/flow-check/2026-02-25-s4-02-signoff.txt`.
- `2026-02-25`: Triển khai checker visual regression có ngưỡng fail/pass bằng script `scripts/check_visual_regression.php`, manifest baseline tại `output/playwright/flow-check/visual-regression.manifest.json`, report chạy thử tại `output/playwright/flow-check/visual-regression-check.txt`.
- `2026-02-25`: Bổ sung policy lưu/cleanup artifact tại `docs/PLAYWRIGHT_ARTIFACT_RETENTION.md` để chuẩn hóa việc giữ bằng chứng và dọn file tạm.
- `2026-02-25`: Dọn toàn bộ artifact legacy không còn phục vụ build/dev/docs (nhóm `patient-*.png`, `qc-current-test.png`, bộ `qc-2026-02-24-tab-*.png` duplicate) và giữ lại baseline/evidence được tham chiếu trong report.
- `2026-02-25`: Hoàn tất mở rộng manifest regression cho `modal chẩn đoán răng` + 4 empty state core tabs; có sign-off tại `output/playwright/flow-check/2026-02-25-x04-modal-empty-signoff.txt`.

Công việc đang làm (WIP):
- Sprint 4 đã chốt toàn bộ `S4-01..S4-06`.
- Các hạng mục review UI/visual regression đang theo dõi đã hoàn tất trong đợt này.

---

### TICKET S4-01 (P0)
- **Title**: Tách business/query logic khỏi `view-patient.blade.php`
- **Type**: Story (BE + FE)
- **Estimate**: 8 SP
- **Dependencies**: Không
- **Scope**:
  - Đưa aggregate/query sang page class hoặc service/view-model.
  - Blade chỉ render data đã chuẩn bị.
- **Acceptance Criteria (QA)**:
  1. Given mở tab hồ sơ, when inspect, then không còn query nghiệp vụ trực tiếp trong Blade.
  2. Given dữ liệu lớn, when load trang, then thời gian phản hồi không tệ hơn baseline hiện tại.

### TICKET S4-02 (P1)
- **Title**: Chuẩn hóa Design Token/CSS system cho patient workspace
- **Type**: Story (FE)
- **Estimate**: 8 SP
- **Dependencies**: S4-01
- **Scope**:
  - Typography, spacing scale, color palette, button styles, badge styles.
  - Loại bỏ inline style dư thừa ở tab lõi.
- **Acceptance Criteria (QA)**:
  1. Given chuyển qua các tab lõi, when so sánh style, then font-size/padding/color nhất quán theo token.
  2. Given dark/light mode (nếu có), when render, then không vỡ tương phản/cấu trúc.

### TICKET S4-03 (P1)
- **Title**: Pixel-level parity 1:1 cho 4 tab lõi
- **Type**: Story (FE + QA)
- **Estimate**: 8 SP
- **Dependencies**: S4-02
- **Scope**:
  - Tab `Lịch hẹn`, `Đơn thuốc`, `Thư viện ảnh`, `Thanh toán`.
  - Header table, empty-state, filter/search/button/badge, khoảng cách dọc-ngang.
- **Acceptance Criteria (QA)**:
  1. Given bộ ảnh tham chiếu, when so sánh screenshot staging, then lệch nhỏ hơn ngưỡng QA đã định.
  2. Given có dữ liệu và không dữ liệu, when chuyển tab, then layout không nhảy/vỡ.

### TICKET S4-04 (P1)
- **Title**: Responsive + horizontal overflow chuẩn cho tab/table
- **Type**: Story (FE)
- **Estimate**: 5 SP
- **Dependencies**: S4-02
- **Scope**:
  - 1366px, 1024px, 768px: tabs/table giữ usability, không che action chính.
- **Acceptance Criteria (QA)**:
  1. Given viewport 1366/1024/768, when thao tác filter/sort/pagination, then vẫn dùng được đầy đủ.
  2. Given bảng nhiều cột, when scroll ngang, then header/body đồng bộ, không lệch cột.

### TICKET S4-05 (P2)
- **Title**: Visual regression baseline cho patient workspace
- **Type**: QA Task
- **Estimate**: 5 SP
- **Dependencies**: S4-03, S4-04
- **Scope**:
  - Snapshot baseline cho các tab lõi + modal chẩn đoán răng + empty states.
- **Acceptance Criteria (QA)**:
  1. Có baseline snapshot đã duyệt.
  2. CI cảnh báo khi UI lệch vượt ngưỡng.

### TICKET S4-06 (P2)
- **Title**: Cleanup artifact/temporary files trong repo + chuẩn lint docs
- **Type**: Task (ENG)
- **Estimate**: 3 SP
- **Dependencies**: Không
- **Scope**:
  - Rà và dọn file tạm/artefact không dùng.
  - Giữ lại tài liệu cần thiết cho audit/trace.
- **Acceptance Criteria (QA)**:
  1. Given rà repo, when check file rác, then không còn artifact không phục vụ build/dev/docs.
  2. Given onboarding dev mới, when clone và chạy app, then không bị nhiễu bởi file tạm.

---

## 6) Backlog cross-sprint (nên tạo trước, kéo dần theo năng lực)

### TICKET X-01 (P1)
- **Title**: Hoàn thiện data dictionary (entity, field, enum, trạng thái)
- **Type**: PM/BA Task
- **Estimate**: 3 SP
- **Acceptance Criteria (QA/BA)**:
  1. Có tài liệu chuẩn cho `Customer/Patient/Appointment/TreatmentPlan/Invoice/Payment/CareTicket`.
  2. QA dùng tài liệu này để viết test case mà không cần đoán nghiệp vụ.

### TICKET X-02 (P1)
- **Title**: UAT script theo vai trò (Lễ tân, Bác sĩ, CSKH, Thu ngân, Manager)
- **Type**: QA Task
- **Estimate**: 5 SP
- **Acceptance Criteria (QA)**:
  1. Có UAT checklist end-to-end cho từng vai trò.
  2. UAT pass trước release production.

### TICKET X-03 (P1)
- **Title**: Audit log thay đổi dữ liệu nghiệp vụ trọng yếu
- **Type**: Story (BE)
- **Estimate**: 8 SP
- **Acceptance Criteria (QA)**:
  1. Track được ai sửa gì, khi nào cho conversion, invoice/payment, care ticket.
  2. Manager có thể truy vết từ UI hoặc log viewer.

### TICKET X-04 (P1)
- **Title**: Mở rộng visual regression baseline cho modal + empty states patient workspace
- **Type**: QA Task
- **Estimate**: 3 SP
- **Status**: Done (`2026-02-25`)
- **Acceptance Criteria (QA)**:
  1. `visual-regression.manifest.json` có thêm case cho `modal chẩn đoán răng` và các empty state chính.
  2. `composer qa:visual-regression` pass với ngưỡng được định nghĩa cho toàn bộ case cũ + mới.
  3. Có report sign-off kèm screenshot evidence cho các case mới.

---

## 7) Ưu tiên release và kiểm soát rủi ro

### Candidate release gate

1. **Release A (cuối Sprint 2)**: sẵn sàng vận hành core (identity + billing ổn định).
2. **Release B (cuối Sprint 4)**: đạt parity UI/flow mức cao theo tham chiếu.

### Rủi ro cần PM theo dõi sát

1. Data migration conflict khi enforce unique.
2. Đổi chuẩn mã hồ sơ ảnh hưởng tích hợp ngoài (in ấn, export, BI).
3. Regression UI khi refactor blade lớn.
4. CSKH cũ và mới chạy song song gây lệch số liệu nếu không cutover rõ.

---

## 8) Checklist QA tổng hợp trước Go-live

1. Lead -> Appointment -> Completed -> Patient conversion không tạo trùng.
2. Chẩn đoán răng -> Kế hoạch -> Tiến trình -> Thanh toán chạy end-to-end.
3. Invoice overdue/partial/paid/cancelled phản ánh đúng ở tab và report.
4. CSKH ticket sinh từ no-show/đơn thuốc/sau điều trị/sinh nhật xuất hiện đúng.
5. Các tab lõi (Lịch hẹn/Đơn thuốc/Thư viện ảnh/Thanh toán) pass pixel checklist.
6. Không còn lỗi SQL runtime kiểu “table not found” trên module đã publish menu.
