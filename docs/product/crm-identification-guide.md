# CRM Identification System - User Guide

## Tổng quan

Hệ thống CRM Identification cung cấp khả năng tự động phát hiện và quản lý các bản ghi trùng lặp trong hệ thống quản lý nha khoa. Tính năng này giúp duy trì chất lượng dữ liệu và ngăn chặn việc tạo ra các bản ghi khách hàng/bệnh nhân trùng lặp.

## Tính năng chính

### 1. Phát hiện trùng lặp tự động
- Quét tự động các bản ghi Customer và Patient
- Sử dụng thuật toán fuzzy matching thông minh
- Tính toán điểm tin cậy (confidence score) từ 0-100%
- Hỗ trợ nhiều tiêu chí matching: tên, số điện thoại, email, ngày sinh

### 2. Quản lý trùng lặp
- Giao diện quản lý trực quan trong Filament Admin
- Xem xét và phê duyệt các trùng lặp được phát hiện
- Gộp bản ghi an toàn với đầy đủ audit trail
- Khả năng rollback trong 24 giờ

### 3. Xác thực danh tính
- Xác minh nhanh thông tin khách hàng
- Cập nhật thông tin liên lạc
- Ghi log đầy đủ cho compliance

## Hướng dẫn sử dụng

### Quét phát hiện trùng lặp

#### Qua Command Line
```bash
# Quét tất cả bản ghi
php artisan crm:detect-duplicates

# Quét chỉ customers
php artisan crm:detect-duplicates --model=customer

# Quét với batch size tùy chỉnh
php artisan crm:detect-duplicates --batch-size=100

# Giới hạn số lượng bản ghi
php artisan crm:detect-duplicates --limit=50
```

#### Qua Admin Panel
1. Truy cập **Duplicate Management** trong menu
2. Click **Scan for Duplicates** 
3. Chờ quá trình quét hoàn tất

### Xem danh sách trùng lặp

#### Command Line
```bash
# Xem tất cả trùng lặp pending
php artisan crm:list-duplicates

# Lọc theo status
php artisan crm:list-duplicates --status=pending

# Lọc theo confidence score
php artisan crm:list-duplicates --confidence=85

# Giới hạn kết quả
php artisan crm:list-duplicates --limit=10
```

#### Admin Panel
1. Vào **Duplicate Management**
2. Sử dụng filters để lọc theo status, confidence level
3. Sắp xếp theo confidence score

### Xử lý trùng lặp

#### Review và phê duyệt
1. Click **Review** trên bản ghi trùng lặp
2. Xem thông tin chi tiết 2 bản ghi
3. Kiểm tra matching criteria
4. Chọn:
   - **Approve for Merge**: Phê duyệt gộp
   - **Not a Duplicate**: Không phải trùng lặp

#### Gộp bản ghi
1. Với bản ghi đã được phê duyệt, click **Merge Now**
2. Xác nhận thao tác
3. Hệ thống sẽ:
   - Gộp dữ liệu vào bản ghi primary
   - Chuyển tất cả related records
   - Soft delete bản ghi secondary
   - Tạo audit trail đầy đủ

### Rollback merge
Nếu cần hoàn tác việc gộp trong vòng 24 giờ:
1. Vào **Record Merges** 
2. Tìm merge record
3. Click **Rollback** (nếu có sẵn)

## Thuật toán Matching

### Tiêu chí matching
1. **Tên (40% trọng số)**
   - Exact match: 100 điểm
   - Levenshtein distance < 2: 80 điểm  
   - Soundex match: 60 điểm
   - Partial match: 40 điểm

2. **Số điện thoại (35% trọng số)**
   - Exact match: 100 điểm
   - 7 số cuối giống: 70 điểm
   - Mã vùng + partial: 40 điểm

3. **Email (20% trọng số)**
   - Exact match: 100 điểm
   - Cùng domain + tương tự: 60 điểm
   - Local part tương tự: 30 điểm

4. **Ngày sinh (5% trọng số)**
   - Exact match: 100 điểm
   - Cùng tháng/ngày: 50 điểm

### Ngưỡng confidence
- **90-100%**: Tự động đề xuất merge
- **75-89%**: Cần review thủ công
- **60-74%**: Hiển thị như possible match
- **Dưới 60%**: Không action

## Bảo mật và Compliance

### HIPAA Compliance
- Tất cả hoạt động được log đầy đủ
- Dữ liệu nhạy cảm được mask trong UI
- Kiểm soát truy cập theo role
- Audit trail hoàn chỉnh

### Phân quyền
- **Admin**: Toàn quyền
- **Manager**: Quản lý duplicates và merge
- **Doctor**: Xem và review
- **Receptionist**: Xem basic info

## Troubleshooting

### Lỗi thường gặp

#### "Column not found: updated_at"
- Chạy lại migration: `php artisan migrate`

#### "Service not found"
- Kiểm tra service provider đã được register
- Chạy: `php artisan config:clear`

#### Performance chậm
- Tăng batch size: `--batch-size=200`
- Chạy vào giờ thấp điểm
- Kiểm tra database indexes

### Maintenance

#### Cleanup dữ liệu cũ
```bash
# Xóa detection records cũ hơn 90 ngày
php artisan crm:cleanup-duplicates
```

#### Monitoring
- Theo dõi số lượng pending duplicates
- Kiểm tra performance của matching algorithm
- Review audit logs định kỳ

## API Reference

### IdentificationService
```php
// Phát hiện trùng lặp
$duplicates = $identificationService->detectDuplicates($record);

// Tính confidence score
$score = $identificationService->calculateConfidenceScore($record1, $record2);

// Xác thực danh tính
$verified = $identificationService->verifyIdentity($record, $data);
```

### RecordMergeService
```php
// Preview merge
$preview = $mergeService->previewMerge($primary, $secondary);

// Thực hiện merge
$success = $mergeService->mergeRecords($primary, $secondary);

// Rollback
$success = $mergeService->rollbackMerge($mergeRecord);
```

## Best Practices

1. **Chạy scan định kỳ** - Hàng tuần hoặc hàng tháng
2. **Review nhanh** - Xử lý duplicates trong 24-48h
3. **Backup trước merge** - Luôn có backup trước khi merge số lượng lớn
4. **Training staff** - Đào tạo nhân viên về quy trình
5. **Monitor performance** - Theo dõi hiệu suất và điều chỉnh

## Support

Nếu gặp vấn đề, liên hệ:
- Technical team qua Slack #tech-support
- Tạo ticket trong hệ thống issue tracking
- Email: support@dental-crm.com