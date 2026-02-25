# Playwright Artifact Retention Policy

Cập nhật: 2026-02-25
Phạm vi: `output/playwright/`

## Mục tiêu

- Giữ đủ evidence để audit parity/responsive.
- Tránh phình repo bởi ảnh trung gian trùng lặp.

## Quy tắc giữ file

Giữ lại:
- Baseline đã duyệt (`qc-YYYY-MM-DD-tab-*-desktop-v*` dùng cho parity).
- Baseline/gate cho modal + empty states (`qc-YYYY-MM-DD-tooth-modal-overlay-v*`, `qc-YYYY-MM-DD-empty-*-desktop-v*`).
- Bộ responsive mới nhất có hậu tố chuẩn (ví dụ `-v2`) cho `1366/1024/768`.
- Báo cáo trong `output/playwright/flow-check/`.
- Diff overlay phục vụ review (`output/playwright/diff/`).

Có thể xóa:
- Ảnh smoke tạm (`*smoke*`).
- Bộ ảnh duplicate cùng ngày không nằm trong baseline chính thức.
- Ảnh trung gian thử nghiệm không được tham chiếu trong report/sign-off.

## Lệnh kiểm tra nhanh

```bash
# Kiểm tra report parity/responsive/sign-off
ls -1 output/playwright/flow-check

# Chạy gate visual regression
composer qa:visual-regression
```

## Ghi chú

Khi cập nhật baseline mới, cần cập nhật đồng thời:
- `output/playwright/flow-check/visual-regression.manifest.json`
- report sign-off liên quan trong `output/playwright/flow-check/`
