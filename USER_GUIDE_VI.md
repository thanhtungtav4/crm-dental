# 🦷 Hướng Dẫn Sử Dụng - Kế Hoạch Điều Trị

## Tổng Quan

Hệ thống Kế Hoạch Điều Trị giúp bạn:
- 📋 Tạo kế hoạch điều trị chi tiết cho bệnh nhân
- 🦷 Theo dõi tiến độ theo từng răng cụ thể
- 📸 Lưu trữ ảnh Before/After
- 💰 Quản lý chi phí dự toán và thực tế
- ✅ Cập nhật tiến độ sau mỗi lần khám

---

## 1. Tạo Kế Hoạch Điều Trị Mới

### Bước 1: Vào trang Kế Hoạch Điều Trị
- Click **"Kế hoạch điều trị"** trong menu bên trái
- Click nút **"Tạo mới"**

### Bước 2: Điền thông tin cơ bản
📝 **Thông tin bệnh nhân & kế hoạch:**
- **Bệnh nhân:** Chọn từ danh sách (có thể tìm kiếm)
- **Bác sĩ điều trị:** Chọn bác sĩ phụ trách
- **Chi nhánh:** (Tùy chọn)
- **Độ ưu tiên:** 
  - 🟢 Thấp - Điều trị không gấp
  - 🔵 Bình thường - Điều trị thông thường
  - 🟡 Cao - Cần ưu tiên
  - 🔴 Khẩn cấp - Cần xử lý ngay
- **Tiêu đề:** VD: "Niềng răng chỉnh nha toàn hàm"

📅 **Lịch trình điều trị:**
- **Ngày bắt đầu dự kiến:** Ngày dự định bắt đầu
- **Ngày kết thúc dự kiến:** Ngày dự định hoàn thành
- *Ngày bắt đầu/kết thúc thực tế sẽ tự động cập nhật*

📸 **Hình ảnh Before/After:** (Tùy chọn)
- Click **"Chọn file"** để upload ảnh
- Có thể cắt/chỉnh sửa ảnh trước khi lưu
- Chọn tỷ lệ: 16:9, 4:3, hoặc 1:1

📋 **Trạng thái & Ghi chú:**
- **Trạng thái:** 
  - Nháp (Draft) - Đang soạn
  - Đã duyệt (Approved) - Đã duyệt, chưa bắt đầu
  - Đang thực hiện (In Progress) - Đang điều trị
  - Hoàn thành (Completed) - Đã xong
  - Đã hủy (Cancelled) - Hủy bỏ
- **Ghi chú:** Các thông tin bổ sung

### Bước 3: Lưu kế hoạch
- Click **"Tạo"** ở góc trên bên phải

---

## 2. Thêm Hạng Mục Điều Trị

Sau khi tạo kế hoạch, bạn cần thêm các hạng mục cụ thể:

### Bước 1: Vào tab "Các hạng mục điều trị"
- Mở kế hoạch vừa tạo
- Click tab **"Các hạng mục điều trị"**
- Click **"Thêm hạng mục"**

### Bước 2: Điền thông tin hạng mục

🩺 **Thông tin dịch vụ điều trị:**
- **Dịch vụ:** Chọn từ danh sách dịch vụ
  - *Tên và giá sẽ tự động điền*
- **Tên hạng mục:** Có thể chỉnh sửa
- **🦷 Vị trí răng:** Nhập số răng
  - 1 răng: `16`
  - Nhiều răng: `11,12,13,14`
  - Khoảng răng: `11-14`
  - Kết hợp: `11-18,21-28`
- **Hệ thống đánh số:**
  - **FDI (11-48)** - Hệ thống quốc tế
  - **Universal (1-32)** - Hệ thống Mỹ

💰 **Số lượng & Chi phí:**
- **Số lượng:** Mặc định 1
- **Số lần khám cần thiết:** Số lần cần đến để hoàn thành
- **Chi phí dự toán:** Giá dự kiến (tự động từ dịch vụ)
- **Chi phí thực tế:** Sẽ cập nhật sau

📊 **Trạng thái & Tiến độ:**
- **Trạng thái:** Chờ/Đang/Hoàn thành/Hủy
- **Độ ưu tiên:** Thấp/Bình thường/Cao/Khẩn cấp
- **Số lần đã khám:** Tự động cập nhật
- **Tiến độ (%):** Tự động tính

📸 **Hình ảnh Before/After:** Upload ảnh cho hạng mục này

📝 **Ghi chú:** Thông tin bổ sung về hạng mục

---

## 3. Cập Nhật Tiến Độ Sau Mỗi Lần Khám

### Cách 1: Nút "Hoàn thành 1 lần"
Khi bệnh nhân đến khám và hoàn thành 1 lần điều trị:

1. Vào tab **"Các hạng mục điều trị"**
2. Tìm hạng mục cần cập nhật
3. Click **"Hoàn thành 1 lần"** (nút màu xanh)
4. Xác nhận

**Kết quả tự động:**
- ✅ Số lần đã khám tăng lên 1
- ✅ Tiến độ (%) tự động tính lại
- ✅ Kế hoạch tổng thể tự động cập nhật

### Cách 2: Sửa thủ công
1. Click **"Sửa"** trên hạng mục
2. Cập nhật **Số lần đã khám**
3. Cập nhật **Chi phí thực tế** (nếu có)
4. Thêm ghi chú
5. Lưu

---

## 4. Các Thao Tác Nhanh

### Với Hạng Mục (Plan Items):

**🚀 Bắt đầu điều trị:**
- Hiện khi trạng thái = "Chờ thực hiện"
- Đổi sang "Đang thực hiện"
- Tự động ghi ngày bắt đầu

**✅ Hoàn thành:**
- Đổi tiến độ thành 100%
- Đánh dấu hoàn thành tất cả các lần khám
- Ghi ngày hoàn thành

**🗑️ Xóa:**
- Xóa hạng mục
- Tự động cập nhật lại kế hoạch tổng

### Với Kế Hoạch (Plans):

**✔️ Duyệt kế hoạch:**
- Duyệt kế hoạch nháp
- Sẵn sàng bắt đầu điều trị

**▶️ Bắt đầu điều trị:**
- Bắt đầu kế hoạch đã duyệt
- Ghi ngày bắt đầu thực tế

**🎯 Hoàn thành:**
- Đánh dấu toàn bộ kế hoạch hoàn thành
- Ghi ngày hoàn thành thực tế

---

## 5. Thao Tác Hàng Loạt (Bulk Actions)

Khi cần cập nhật nhiều hạng mục cùng lúc:

### Bước 1: Chọn các hạng mục
- Tick vào checkbox của các hạng mục cần cập nhật

### Bước 2: Chọn thao tác
- **"Đánh dấu Đang thực hiện"** - Chuyển tất cả sang đang thực hiện
- **"Đánh dấu Hoàn thành"** - Hoàn thành tất cả (100%)
- **"Hủy bỏ"** - Hủy các hạng mục đã chọn
- **"Xóa đã chọn"** - Xóa các hạng mục

### Bước 3: Xác nhận
- Hệ thống sẽ hỏi xác nhận
- Click **"Xác nhận"**
- Tự động cập nhật kế hoạch tổng

---

## 6. Hiểu Các Chỉ Số

### Tiến Độ (Progress)
- **0%** = Chưa bắt đầu (màu xám)
- **1-49%** = Mới bắt đầu (màu vàng)
- **50-99%** = Đang thực hiện (màu xanh dương)
- **100%** = Hoàn thành (màu xanh lá)

Công thức: `(Số lần đã khám / Số lần cần thiết) × 100%`

### Số Lần Khám
- Hiển thị: **"8/24 lần"**
- Nghĩa: Đã khám 8 lần, cần 24 lần

### Chênh Lệch Chi Phí
- **Màu đỏ:** Chi phí thực tế > Dự toán (vượt ngân sách)
- **Màu xanh:** Chi phí thực tế < Dự toán (tiết kiệm)
- **Màu xám:** Chưa có chi phí thực tế

Ví dụ:
- Dự toán: 10,000,000đ
- Thực tế: 12,000,000đ
- Chênh lệch: +2,000,000đ (+20%) ⚠️ Vượt ngân sách

---

## 7. Hệ Thống Đánh Số Răng

### FDI (Quốc tế) - Mặc định
```
Hàm trên phải:  18 17 16 15 14 13 12 11 | 21 22 23 24 25 26 27 28  :Hàm trên trái
Hàm dưới phải: 48 47 46 45 44 43 42 41 | 31 32 33 34 35 36 37 38  :Hàm dưới trái
```

### Universal (Mỹ)
```
Hàm trên phải:  1  2  3  4  5  6  7  8 | 9  10 11 12 13 14 15 16  :Hàm trên trái
Hàm dưới phải: 32 31 30 29 28 27 26 25 | 24 23 22 21 20 19 18 17  :Hàm dưới trái
```

### Ví Dụ Nhập Răng
| Nhập | Ý nghĩa | Kết quả |
|------|---------|---------|
| `16` | Răng hàm trên bên phải số 6 | [16] |
| `11-14` | Răng cửa trên bên phải (4 răng) | [11, 12, 13, 14] |
| `16,26,36,46` | 4 răng hàm số 6 (cả 4 góc) | [16, 26, 36, 46] |
| `11-18,21-28` | Toàn bộ hàm trên | [11..18, 21..28] |

---

## 8. Bộ Lọc & Tìm Kiếm

### Lọc theo Trạng thái
- **Nháp:** Chưa duyệt
- **Đã duyệt:** Sẵn sàng bắt đầu
- **Đang thực hiện:** Đang điều trị
- **Hoàn thành:** Đã xong
- **Đã hủy:** Bị hủy

### Lọc theo Độ Ưu Tiên
- **Thấp:** Không gấp
- **Bình thường:** Thông thường
- **Cao:** Ưu tiên
- **Khẩn cấp:** Cần xử lý ngay

### Tìm Kiếm
- Gõ tên bệnh nhân, tên kế hoạch, hoặc tên bác sĩ
- Hệ thống tự động lọc kết quả

---

## 9. Các Trường Hợp Thường Gặp

### Case 1: Niềng Răng (12 tháng)
```
Kế hoạch: Niềng răng chỉnh nha toàn hàm
├─ Hạng mục 1: Lắp mắc cài hàm trên (răng 11-18,21-28) - 1 lần
├─ Hạng mục 2: Lắp mắc cài hàm dưới (răng 31-38,41-48) - 1 lần
└─ Hạng mục 3: Tái khám điều chỉnh - 22 lần (mỗi 2-3 tuần)

Cách cập nhật: Sau mỗi lần tái khám, click "Hoàn thành 1 lần" ở Hạng mục 3
```

### Case 2: Implant (3 tháng)
```
Kế hoạch: Cấy ghép Implant răng số 16
├─ Hạng mục 1: Phẫu thuật cấy implant (răng 16) - 1 lần
├─ Hạng mục 2: Lắp trụ abutment (răng 16) - 1 lần (sau 8 tuần)
└─ Hạng mục 3: Lắp mão răng sứ (răng 16) - 2 lần

Cách cập nhật: Hoàn thành từng hạng mục theo thứ tự
```

### Case 3: Điều Trị Nội Nha (2 tuần)
```
Kế hoạch: Điều trị tủy răng số 26
├─ Hạng mục 1: Mở tủy và làm sạch (răng 26) - 1 lần
├─ Hạng mục 2: Làm sạch và đặt thuốc (răng 26) - 1 lần
└─ Hạng mục 3: Trám bít và phục hồi (răng 26) - 1 lần

Cách cập nhật: Hoàn thành mỗi lần sau mỗi buổi khám
```

---

## 10. Mẹo & Lưu Ý

### ✅ Nên Làm:
- 📸 **Chụp ảnh Before** ngay khi bắt đầu
- 📋 **Chia nhỏ hạng mục** để dễ theo dõi
- 💰 **Cập nhật chi phí thực tế** sau mỗi lần
- 📝 **Ghi chú đầy đủ** các vấn đề đặc biệt
- 🔄 **Cập nhật tiến độ ngay** sau mỗi lần khám

### ⚠️ Lưu Ý:
- **Tiến độ tự động tính** - Không cần nhập thủ công
- **Trạng thái tự chuyển** khi đạt 100%
- **Ngày thực tế tự ghi** khi bắt đầu/hoàn thành
- **Xóa hạng mục** sẽ tự cập nhật lại kế hoạch tổng
- **Kế hoạch quá hạn** sẽ được đánh dấu tự động

### 🎯 Best Practices:
1. **Luôn duyệt kế hoạch** trước khi bắt đầu điều trị
2. **Upload ảnh Before** trước khi điều trị
3. **Cập nhật sau mỗi lần khám** để theo dõi chính xác
4. **Ghi chú khi chi phí thay đổi** để giải trình
5. **Upload ảnh After** khi hoàn thành để so sánh

---

## 11. Câu Hỏi Thường Gặp (FAQ)

**Q1: Tại sao không thể sửa "Tiến độ (%)"?**  
A: Tiến độ tự động tính từ số lần khám. Muốn đổi tiến độ, hãy cập nhật "Số lần đã khám".

**Q2: Làm sao biết kế hoạch quá hạn?**  
A: Kế hoạch quá hạn sẽ hiển thị trong bộ lọc "Overdue" và có màu cảnh báo.

**Q3: Có thể xóa hạng mục đã hoàn thành không?**  
A: Có, nhưng tiến độ tổng sẽ tự động tính lại. Nên cân nhắc trước khi xóa.

**Q4: Tại sao chi phí thực tế lớn hơn dự toán?**  
A: Có thể do:
- Phát sinh thêm vật liệu đặc biệt
- Tình trạng răng phức tạp hơn dự kiến
- Bệnh nhân cần thêm dịch vụ
➡️ Nên ghi chú lý do để giải trình với bệnh nhân

**Q5: Có thể đổi hệ thống đánh số răng sau khi tạo không?**  
A: Có, vào "Sửa" hạng mục và đổi từ FDI sang Universal hoặc ngược lại.

**Q6: Nút "Hoàn thành 1 lần" không hiển thị?**  
A: Nút chỉ hiện khi:
- Trạng thái không phải "Hoàn thành" hoặc "Đã hủy"
- Số lần đã khám < Số lần cần thiết

**Q7: Có thể in kế hoạch cho bệnh nhân không?**  
A: Tính năng in sẽ được thêm trong phiên bản tương lai. Hiện tại có thể chụp màn hình.

---

## 12. Hỗ Trợ

Nếu gặp vấn đề hoặc cần hỗ trợ:

📧 **Email:** support@example.com  
📞 **Hotline:** 1900 XXXX  
💬 **Chat:** Trong hệ thống (góc dưới bên phải)

---

**Phiên bản:** 1.0.0  
**Cập nhật:** 02/11/2025  
**Tài liệu:** Hệ thống Quản lý Nha khoa
