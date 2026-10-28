# PTPMMNM-CNTT1811-NHOM-11
# Đề tài 20: Hệ thống luyện tập và chấm bài lập trình trực tuyến

Hệ thống cho phép người học chọn bài tập, soạn mã trên trình duyệt, chạy thử và nộp bài chấm tự động với bộ điều phối chấm và dịch vụ thực thi cách ly.

## 1. Thành viên thực hiện & Phân công
| STT | Họ và tên | Mã SV | Vai trò & Phần việc phụ trách |

| 1 | Phạm Hoàng Vũ | 1871020673 | Thiết kế CSDL, Bộ điều phối chấm (`worker.php`), Dịch vụ thực thi (`sandbox_service`) |

| 2 | Đặng Thanh Xuân | 1871020679 | Giao diện soạn mã (Monaco Editor), Chạy thử, API Nộp bài & Chống nộp trùng (Idempotency) |

| 3 | Hoàng Anh Quân | 1871020465 | Quản lý bài tập, Tạo phiên bản bộ dữ liệu mới, Chấm lại (Rejudge) & Kiểm thử tự động |

## 2. Giấy phép & Nguồn dữ liệu bài tập hợp lệ
- **Giấy phép mã nguồn:** Dự án được phân phối dưới giấy phép **MIT License** (xem tệp `LICENSE`).
- **Nguồn sử dụng bộ 10 bài tập:**
  1. Các bài tập cơ bản (Bài 1 - Bài 5): Nhóm tự biên soạn đề bài, tự sinh bộ dữ liệu kiểm thử (`test_cases`) và viết mã giải mẫu (Phát hành theo giấy phép CC0 1.0 Public Domain).
  2. Các bài tập thuật toán (Bài 6 - Bài 10): Tham khảo và biên tập lại từ kho bài tập mở [CSES Problem Set](https://cses.fi/problemset/) / [Project Euler](https://projecteuler.net/) (CC BY-NC-SA 4.0), bộ dữ liệu kiểm thử (`stdin`/`stdout`) được nhóm tự viết generator sinh mới hoàn toàn.
- **Thư viện bên thứ ba sử dụng:**
  - Bộ soạn mã trên trình duyệt: [Monaco Editor](https://microsoft.github.io/monaco-editor/) (MIT License).

## 3. Kiến trúc hệ thống & An toàn
Hệ thống được tách thành 3 tiến trình độc lập (Tuyệt đối không chạy mã nộp trong tiến trình Web):
1. **Web Server & API (Apache / PHP) + MySQL:** Xử lý giao diện, phân quyền, nhận bài nộp vào hàng đợi.
2. **Bộ điều phối chấm (`worker/worker.php`):** Tiến trình PHP CLI độc lập lấy bài từ hàng đợi, gửi qua Sandbox, tự so sánh `stdout` và tổng hợp kết quả.
3. **Dịch vụ thực thi cách ly (`sandbox_service/server.php`):** Chạy ở cổng riêng (`127.0.0.1:4000`), thực thi mã Python/C++ trong thư mục tạm ngoài Web root với giới hạn thời gian chạy.

## 4. Hướng dẫn cài đặt và chạy lại hệ thống (XAMPP + VS Code)
### Yêu cầu môi trường
- XAMPP (PHP >= 8.0, MySQL / MariaDB)
- Python 3.x và MinGW-w64 (`g++`) đã thêm vào biến môi trường `PATH` của Windows.

### Các bước chạy hệ thống
1. **Khởi tạo CSDL:**
   - Bật **Apache** và **MySQL** trên XAMPP.
   - Mở **MySQL Workbench**, chạy tệp `database/schema_and_seed.sql` để tạo 6 bảng dữ liệu và nạp sẵn 10 bài tập (mỗi bài >= 5 test cases).
2. **Cấu hình kết nối:**
   - Kiểm tra thông tin kết nối MySQL trong tệp `config/db.php`.
3. **Khởi động Dịch vụ thực thi cách ly (Terminal 1 trên VS Code):**
   ```powershell
   php -S 127.0.0.1:4000 sandbox_service/server.php
