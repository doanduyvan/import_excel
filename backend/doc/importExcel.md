## Chức năng: Import file Excel từ email vào database qua cron job

### 🎯 Mô tả:

Hệ thống định kỳ kiểm tra email để tìm file `.zip`, giải nén và import dữ liệu từ file Excel vào database. Sau khi xử lý, hệ thống gửi email thông báo kết quả thành công hoặc thất bại.

---

### 🔄 Luồng nghiệp vụ:

1. **Cron job** trên hosting Linux chạy định kỳ (ví dụ: mỗi 5 phút).
2. Hệ thống kết nối vào hộp thư đến qua **IMAP**.
3. Quét danh sách các **email chưa đọc**.
4. Với mỗi email:
    - Kiểm tra có file đính kèm định dạng `.zip` không.
    - Nếu có: 5. Giải nén file `.zip`. 6. Lấy file `.xlsx` bên trong. 7. Lưu file Excel vào thư mục `storage/app/excel/`. 8. Đọc nội dung file Excel (sử dụng mapping cột phù hợp). 9. Ghi dữ liệu vào database (ví dụ: bảng `users`). 10. Đánh dấu email là **đã xử lý** (optional). 11. Ghi log quá trình xử lý. 12. Gửi email thông báo đến địa chỉ cấu hình sẵn: - Tiêu đề: `Import thành công` hoặc `Import thất bại` - Nội dung: báo cáo lỗi (nếu có)

---

### 📥 Input:

-   Email có file `.zip` đính kèm, chứa file `.xlsx`
-   Định dạng file Excel đúng cấu trúc (VD: A = name, B = email...)

---

### 📤 Output:

-   Dữ liệu lưu vào DB (ví dụ bảng `users`)
-   Ghi log xử lý thành công/thất bại
-   Gửi email báo kết quả xử lý
