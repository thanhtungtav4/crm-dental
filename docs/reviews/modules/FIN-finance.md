# Metadata

- Module code: `FIN`
- Module name: `Finance / Payments / Wallet / Installments`
- Current status: `Planning`
- Current verdict: `D`
- Review file: `docs/reviews/modules/FIN-finance.md`
- Issue file: `docs/issues/FIN-issues.md`
- Plan file: `docs/planning/FIN-plan.md`
- Issue ID prefix: `FIN-`
- Task ID prefix: `TASK-FIN-`
- Dependencies: `GOV, PAT, APPT, TRT, INV, KPI`
- Last updated: `2026-03-06`

# Scope

- Review module `FIN` theo 4 lớp: architecture, database, domain logic, UI/UX.
- Trong phạm vi review:
  - `invoices`, `payments`, `installment_plans`, `patient_wallets`, `wallet_ledger_entries`
  - observer/service/command cho thanh toán, ví bệnh nhân, overdue, dunning, branch attribution
  - Filament resources/page/form/table/relation manager của invoice, payment, patient wallet
- Flow chính được xem xét:
  - tạo hóa đơn
  - ghi nhận thanh toán / hoàn tiền / đảo phiếu
  - cập nhật ví bệnh nhân
  - theo dõi trả góp / reminder overdue
  - điều chỉnh ví và đối soát branch attribution

# Context

- `FIN` là boundary chạm trực tiếp tới tiền, công nợ, hoàn tiền, cọc, ví bệnh nhân và KPI doanh thu.
- Đây là module nhạy cảm nhất sau `TRT` vì mọi drift ở state invoice, reversal, wallet ledger hoặc delete surface đều tác động thẳng tới kế toán và truy vết pháp lý.
- Thông tin còn thiếu làm giảm độ chính xác review:
  - chưa có SOP chính thức cho `void invoice`, `credit note`, `refund approval`
  - chưa có ma trận quyền tài chính chi tiết ngoài baseline permission hiện tại
  - chưa có quy định vận hành về người được phép điều chỉnh số dư ví và ngưỡng phê duyệt

# Executive Summary

🚨 CẢNH BÁO NGHIÊM TRỌNG

- `PatientWalletResource` hiện không có policy riêng; runtime check cho thấy `Doctor` có thể `canViewAny()` và `canEdit()`, trong khi action `adjust` gọi thẳng `PatientWalletService::adjustBalance()` mà không có `ActionPermission` hoặc audit log chuyên biệt.
- Invoice lifecycle vẫn cho phép đi thẳng vào `cancelled` từ form edit, và `updatePaymentStatus()` sẽ short-circuit nếu invoice đã `cancelled`; điều này mở đường hủy hóa đơn dù đã có payment/refund mà không qua workflow canonical.
- Luồng refund đang gọi `markReversed()` rồi `recordPayment()` từ nhiều UI surface khác nhau nhưng không khóa record gốc và không có unique guard trên `reversal_of_id`; hai thao tác đồng thời có thể tạo nhiều phiếu đảo cho cùng một khoản thu.

- Mức độ an toàn hiện tại: `Kém`
- Mức độ rủi ro nghiệp vụ: `Rất cao`
- Mức độ sẵn sàng production: `Kém`, chưa đạt baseline an toàn cho module tài chính
- Điểm tốt đáng kể:
  - `Invoice::recordPayment()` đã có transaction, `lockForUpdate()` trên invoice và idempotency theo `transaction_ref`
  - wallet ledger đã có unique `(payment_id, entry_type)` cho một số nhánh idempotency
  - branch attribution cho invoice/payment đã có reconciliation command và regression tests

# Architecture Findings

## Security

- Đánh giá: `Kém`
- Evidence:
  - `app/Filament/Resources/PatientWallets/PatientWalletResource.php`
  - `app/Filament/Resources/PatientWallets/Tables/PatientWalletsTable.php`
  - `app/Services/PatientWalletService.php`
  - `app/Filament/Resources/Payments/Schemas/PaymentForm.php`
  - `app/Filament/Resources/Invoices/RelationManagers/PaymentsRelationManager.php`
  - runtime check: `PatientWalletResource::canViewAny() === true`, `PatientWalletResource::canEdit(new PatientWallet()) === true` cho user role `Doctor`
- Findings:
  - `PatientWalletResource` không có policy, nhưng lại mở `EditAction` và `adjust` action cho ví bệnh nhân.
  - `adjustBalance()` không yêu cầu action permission riêng và không ghi audit log tài chính chuyên biệt.
  - `received_by` trong `PaymentForm` và `PaymentsRelationManager` chưa branch-scoped và chưa sanitize server-side.
- Suggested direction:
  - thêm `PatientWalletPolicy`, tách action permission `wallet_adjust`, khóa resource ví về read-only trừ finance/admin.
  - scope `received_by` theo branch accessible và sanitize ở page/relation manager.

## Data Integrity & Database

- Đánh giá: `Kém`
- Evidence:
  - schema `invoices`, `payments`, `patient_wallets`, `wallet_ledger_entries`
  - `app/Models/Invoice.php`
  - `app/Models/Payment.php`
  - `app/Models/WalletLedgerEntry.php`
  - `app/Filament/Resources/Invoices/Pages/EditInvoice.php`
  - `app/Filament/Resources/Invoices/Tables/InvoicesTable.php`
- Findings:
  - `invoices` và `payments` vẫn để destructive flow khá mềm: invoice có `DeleteAction`, `DeleteBulkAction`, policy còn mở `delete/restore/forceDelete`.
  - `payments.invoice_id` là `cascadeOnDelete`; nếu force delete invoice thì payment có thể biến mất, còn `wallet_ledger_entries.payment_id` lại `nullOnDelete`, làm yếu traceability.
  - `Invoice::EDITABLE_STATUSES` vẫn cho phép `cancelled`, và `InvoiceForm` cho phép set trực tiếp trạng thái này.
- Suggested direction:
  - khóa delete/force delete ở finance core, ưu tiên `cancel/void` qua workflow service.
  - siết FK strategy và policy để lịch sử tài chính không mất liên kết gốc.

## Concurrency / Race-condition

- Đánh giá: `Kém`
- Evidence:
  - `app/Models/Payment.php:143-168`
  - `app/Filament/Resources/Payments/Tables/PaymentsTable.php`
  - `app/Filament/Resources/Invoices/RelationManagers/PaymentsRelationManager.php`
  - `app/Filament/Resources/Invoices/Tables/InvoicesTable.php`
- Findings:
  - `markReversed()` chỉ update metadata trên payment gốc, không `lockForUpdate()` và không có unique constraint đảm bảo một reversal duy nhất.
  - logic refund được lặp ở `PaymentsTable` và `PaymentsRelationManager`, cùng pattern `markReversed() -> recordPayment()`.
  - invoice `recordPayment()` idempotent theo `transaction_ref`, nhưng refund UI hiện đang để `transactionRef = null`, nên thiếu idempotency guard.
- Suggested direction:
  - tạo `PaymentReversalService` transaction-safe, row-lock original payment + invoice, idempotent theo original payment.
  - thêm unique/index cho reversal canonical path.

## Performance / Scalability

- Đánh giá: `Trung bình`
- Evidence:
  - `app/Filament/Resources/Invoices/InvoiceResource.php`
  - `app/Filament/Resources/Payments/PaymentResource.php`
  - `app/Console/Commands/RunInvoiceAgingReminders.php`
  - `app/Console/Commands/RunInstallmentDunning.php`
- Findings:
  - điểm tốt: invoice list đã `withSum('payments as payments_sum_amount', 'amount')` và `withExists('payments')`.
  - điểm yếu: refund/payment create logic bị nhân bản ở nhiều UI surface, tăng chi phí bảo trì và nguy cơ drift query.
  - commands aging/dunning chunk toàn cục nhưng chưa có boundary service chung cho finance workflow.
- Suggested direction:
  - gom payment/refund flow vào service canonical.
  - tiếp tục dùng aggregate eager load thay vì `getTotalPaid()` rải rác trên mọi surface.

## Maintainability

- Đánh giá: `Trung bình`
- Evidence:
  - `app/Filament/Resources/Payments/Pages/CreatePayment.php`
  - `app/Filament/Resources/Payments/Tables/PaymentsTable.php`
  - `app/Filament/Resources/Invoices/RelationManagers/PaymentsRelationManager.php`
  - `app/Filament/Resources/Invoices/Tables/InvoicesTable.php`
- Findings:
  - cùng một nghiệp vụ `record payment / refund / duplicate transaction ref` đang sống ở nhiều nơi.
  - `EditPayment` page vẫn tồn tại dù `Payment` model cấm update/delete và `PaymentResource` không publish page edit.
  - wallet UI vừa có `EditAction`, vừa có `adjust`, nhưng form chỉ là placeholder; surface hiện tại dễ gây hiểu nhầm.
- Suggested direction:
  - rút gọn finance write surfaces, giữ 1 service và 1-2 entry point rõ ràng.
  - loại bỏ dead surface hoặc align hẳn với domain invariant immutable.

## Better Architecture Proposal

- Tách `FIN` thành 4 boundary nghiệp vụ rõ ràng:
  - `InvoiceWorkflowService` cho issue/cancel/void invoice
  - `PaymentRecordingService` cho receipt/deposit/wallet spend theo invoice
  - `PaymentReversalService` cho refund/reversal idempotent
  - `PatientWalletAdjustmentService` cho manual adjustment có permission + audit riêng
- Mục tiêu kiến trúc:
  - mọi trạng thái terminal của invoice đi qua workflow service
  - mọi refund/reversal có khóa transaction và idempotency guard
  - ví bệnh nhân là read-mostly ledger, manual adjustment là action đặc biệt có audit

# Domain Logic Findings

## Workflow chính

- Đánh giá: `Kém`
- Workflow hiện tại:
  - tạo invoice
  - ghi nhận payment/refund
  - đồng bộ paid amount / overdue / installment
  - update wallet ledger nếu có deposit/wallet flow
- Nhận xét:
  - `recordPayment()` ở domain layer đã khá tốt cho receipt thông thường.
  - nhưng invoice cancel/delete, refund và wallet adjustment vẫn chưa có canonical workflow service đủ chặt.

## State transitions

- Đánh giá: `Kém`
- Hiện có:
  - `Invoice.status`: `draft`, `issued`, `partial`, `paid`, `overdue`, `cancelled`
  - `Payment.direction`: `receipt`, `refund`
  - `InstallmentPlan.status`: `active`, `completed`, `defaulted`, `cancelled`
- Vấn đề:
  - invoice có thể bị set `cancelled` trực tiếp từ form thay vì qua service.
  - `updatePaymentStatus()` không xử lý gì thêm nếu invoice đã `cancelled`, nên cancellation có thể che giấu payment state thực.
  - refund chưa có state machine hay reversal invariant ở payment gốc.

## Missing business rules

- chưa có rule rõ ràng `ai được điều chỉnh ví bệnh nhân`.
- chưa có rule `invoice đã có payment/installment` thì chỉ được void/cancel theo quy trình kiểm soát.
- chưa có rule `mỗi payment receipt chỉ được reversal một lần` ở DB + domain.
- chưa có rule branch-scope cho `received_by`.

## Invalid states / forbidden transitions

- user có thể cancel invoice đã có payment mà không reverse các payment trước.
- cùng một payment có thể bị hoàn/đảo nhiều lần nếu bấm đồng thời ở nhiều tab.
- doctor không thuộc finance vẫn có thể nhìn thấy và sửa wallet surface do resource không có policy.
- soft delete invoice có thể làm UI tài chính drift, hard delete còn có nguy cơ làm mất liên kết trace của payment/ledger.

## Service / action / state machine / transaction boundary de xuat

- Tạo `InvoiceWorkflowService`:
  - `issue()`
  - `cancel()`
  - `void()` nếu nghiệp vụ cần
- Tạo `PaymentReversalService`:
  - lock original payment + invoice
  - return existing reversal nếu đã có
- Tạo `PatientWalletAdjustmentService`:
  - `ActionPermission` riêng
  - mandatory reason
  - audit log structured context
- Tạo `FinanceActorAuthorizer`:
  - scope `received_by`
  - sanitize server-side cho create payment/refund flows

# QA/UX Findings

## User flow

- Đánh giá: `Trung bình`
- Flow tốt:
  - tạo payment từ invoice/patient context khá nhanh.
  - invoice list đã hiển thị progress, balance, overdue.
- Friction/risk lớn:
  - wallet có `EditAction` và `adjust` nhưng không thể hiện rõ đây là thao tác nhạy cảm.
  - refund action xuất hiện ở nhiều nơi nhưng không cho thấy guard/idempotency rõ ràng.
  - invoice vẫn có delete surface thay vì hướng người dùng vào `cancel/void`.

## Filament UX

- Đánh giá: `Trung bình`
- Evidence:
  - `app/Filament/Resources/Invoices/Tables/InvoicesTable.php`
  - `app/Filament/Resources/Payments/Tables/PaymentsTable.php`
  - `app/Filament/Resources/PatientWallets/Tables/PatientWalletsTable.php`
- Findings:
  - `received_by` chọn toàn bộ user làm tăng khả năng hạch toán sai người nhận tiền.
  - payment/refund modal chưa giải thích rõ một reversal là thao tác một lần, không thể lặp.
  - wallet resource thiếu cảnh báo hậu quả khi adjust số dư.

## Edge cases quan trọng

- hai kế toán cùng hoàn tiền một phiếu thu trong hai tab khác nhau.
- invoice đã có installment plan nhưng bị cancel trực tiếp từ form edit.
- thu tiền cho invoice chi nhánh A nhưng gán `received_by` thuộc chi nhánh B.
- soft delete invoice còn payment/ledger làm payment list mất liên kết hiển thị.
- manual wallet adjustment âm vượt số dư.
- retry request tạo payment với `transaction_ref` trùng.
- invoice overdue đã có reminder nhưng được thanh toán đủ giữa lúc command đang chạy.

## Điểm dễ thao tác sai

- chọn `cancelled` trực tiếp trong form invoice.
- dùng `DeleteAction` thay vì flow nghiệp vụ `cancel/void`.
- dùng wallet `adjust` như một thao tác thường ngày dù không có guard quyền rõ ràng.
- nhập `received_by` sai chi nhánh vì dropdown không scope.

## Đề xuất cải thiện UX

- bỏ `DeleteAction` khỏi invoice và thay bằng `Cancel/Void` action có modal xác nhận.
- wallet chỉ nên có `View` + `Adjust` cho role finance hợp lệ, kèm modal bắt buộc lý do.
- refund UI nên hiển thị rõ `phiếu gốc`, `đã đảo chưa`, và disable ngay nếu reversal đã tồn tại.
- scope `received_by` theo branch và thêm helper text `chỉ hiển thị người thu thuộc phạm vi chi nhánh`.

# Issue Summary

| Issue ID | Severity | Category | Title | Status | Short note |
| --- | --- | --- | --- | --- | --- |
| FIN-001 | Critical | Security | Wallet resource khong co policy va mo duong dieu chinh so du qua rong | Open | Doctor runtime van xem/sua wallet surface, adjust khong co action permission |
| FIN-002 | Critical | Domain Logic | Invoice lifecycle cho phep cancel truc tiep va bypass workflow tai chinh | Open | Form va model cho phep set `cancelled` khong qua service canonical |
| FIN-003 | Critical | Concurrency | Refund/reversal khong idempotent va co race-condition tren payment goc | Open | Nhieu UI surface goi `markReversed() -> recordPayment()` khong co lock/unique |
| FIN-004 | High | Data Integrity | Delete surface cua invoice va finance core van de drift du lieu xuong dong | Open | DeleteAction/DeleteBulkAction va FK strategy hien tai chua an toan |
| FIN-005 | High | Security | `received_by` chua branch-scoped va chua sanitize server-side | Open | Co the gan nguoi thu sai chi nhanh tren payment/refund flow |
| FIN-006 | Medium | Maintainability | Finance write logic bi nhan ban qua nhieu surface UI | Open | Create/refund flow dang lap lai o page, table, relation manager |
| FIN-007 | Medium | Maintainability | Regression suite chua khoa wallet auth, invoice cancel hole va concurrent reversal | Open | Chua thay test chan 3 lo hong nghiem trong nhat cua FIN |

# Dependencies

- `GOV`: RBAC/action permission cho refund, wallet adjust, automation run
- `PAT`: patient ownership va wallet owner
- `APPT`: visit context, branch attribution timeline
- `TRT`: invoice lien ket treatment plan/session
- `INV`: giao diem voi thu/chi va batch consumption follow-up
- `KPI`: revenue/aging/collection reporting

# Open Questions

- Invoice đã có payment thì nghiệp vụ muốn `cancel`, `void`, hay bắt buộc tạo chứng từ điều chỉnh riêng?
- Wallet adjustment có cần quy trình 2 bước phê duyệt hay chỉ cần permission + audit?
- Refund có cho phép nhiều lần trên cùng phiếu gốc ở dạng partial reversal hay chỉ một reversal canonical?

# Recommended Next Steps

- Tao issue file canonical cho module
- Tao plan file cho module
- Fix truoc `FIN-001`, `FIN-002`, `FIN-003` vi day la 3 blocker tac dong truc tiep toi an toan tai chinh

# Current Status

- Planning
