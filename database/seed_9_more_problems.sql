USE online_judge_db;

-- Xóa dữ liệu cũ của bài 2..10 (nếu chạy lại nhiều lần) để tránh trùng khóa chính
DELETE FROM problems WHERE id BETWEEN 2 AND 10;

-- =================================================================================
-- NẠP 9 BÀI TẬP (BÀI 2 -> BÀI 10) ĐỦ CHỦ ĐỀ, MỨC ĐỘ VÀ NGUỒN HỢP LỆ
-- =================================================================================
INSERT INTO problems (id, title, topic, difficulty, description, io_description, public_examples, current_version_id, source_credit) VALUES
(2, 'Bài 2: Kiểm tra số chẵn lẻ', 'Rẽ nhánh', 'easy',
 'Cho một số nguyên n. Hãy kiểm tra xem n là số chẵn hay số lẻ.',
 'Đầu vào: Một số nguyên n (-10^18 <= n <= 10^18).\nĐầu ra: In ra EVEN nếu n chẵn, ngược lại in ra ODD.',
 '[{"stdin": "4", "stdout": "EVEN"}, {"stdin": "7", "stdout": "ODD"}]',
 2, 'Nhóm 11 tự biên soạn (CC0 1.0)'),

(3, 'Bài 3: Tìm số lớn nhất trong 3 số', 'Rẽ nhánh', 'easy',
 'Cho ba số nguyên a, b, c. Hãy tìm và in ra giá trị lớn nhất trong ba số đó.',
 'Đầu vào: Một dòng chứa 3 số nguyên a, b, c cách nhau bởi khoảng trắng (-10^9 <= a, b, c <= 10^9).\nĐầu ra: Giá trị lớn nhất.',
 '[{"stdin": "3 9 5", "stdout": "9"}, {"stdin": "-5 -2 -10", "stdout": "-2"}]',
 3, 'Nhóm 11 tự biên soạn (CC0 1.0)'),

(4, 'Bài 4: Tính tổng từ 1 đến N', 'Số học', 'easy',
 'Cho số nguyên dương n. Hãy tính tổng S = 1 + 2 + ... + n. Lưu ý n có thể lên tới 10^9 nên cần dùng công thức toán học thay vì vòng lặp đơn thuần.',
 'Đầu vào: Một số nguyên dương n (1 <= n <= 10^9).\nĐầu ra: Tổng các số từ 1 đến n.',
 '[{"stdin": "5", "stdout": "15"}, {"stdin": "10", "stdout": "55"}]',
 4, 'Nhóm 11 tự biên soạn (CC0 1.0)'),

(5, 'Bài 5: Tính giai thừa N!', 'Vòng lặp', 'easy',
 'Cho số nguyên không âm n. Hãy tính n! (n giai thừa), biết 0! = 1.',
 'Đầu vào: Một số nguyên n (0 <= n <= 20).\nĐầu ra: Giá trị của n!.',
 '[{"stdin": "5", "stdout": "120"}, {"stdin": "0", "stdout": "1"}]',
 5, 'Nhóm 11 tự biên soạn (CC0 1.0)'),

(6, 'Bài 6: Kiểm tra số nguyên tố', 'Số học', 'medium',
 'Cho số nguyên n. Hãy kiểm tra xem n có phải là số nguyên tố hay không.',
 'Đầu vào: Một số nguyên n (-10^9 <= n <= 10^9).\nĐầu ra: In ra YES nếu n là số nguyên tố, ngược lại in ra NO.',
 '[{"stdin": "7", "stdout": "YES"}, {"stdin": "9", "stdout": "NO"}]',
 6, 'Tham khảo CSES / Toán học cổ điển (CC BY-NC-SA 4.0)'),

(7, 'Bài 7: Ước chung lớn nhất (GCD)', 'Số học', 'medium',
 'Cho hai số nguyên dương a và b. Hãy tìm ước chung lớn nhất của hai số đó bằng thuật toán Euclid.',
 'Đầu vào: Hai số nguyên dương a và b (1 <= a, b <= 10^18).\nĐầu ra: Ước chung lớn nhất của a và b.',
 '[{"stdin": "12 18", "stdout": "6"}, {"stdin": "17 13", "stdout": "1"}]',
 7, 'Thuật toán Euclid cổ điển (Public Domain)'),

(8, 'Bài 8: Số Fibonacci thứ N', 'Quy hoạch động', 'medium',
 'Dãy Fibonacci được định nghĩa: F(0) = 0, F(1) = 1, F(n) = F(n-1) + F(n-2) với n >= 2. Cho n, hãy tìm F(n).',
 'Đầu vào: Một số nguyên n (0 <= n <= 80).\nĐầu ra: Giá trị F(n).',
 '[{"stdin": "6", "stdout": "8"}, {"stdin": "10", "stdout": "55"}]',
 8, 'Tham khảo Project Euler / Cổ điển (CC BY-NC-SA 4.0)'),

(9, 'Bài 9: Đếm số nguyên âm trong chuỗi', 'Xử lý chuỗi', 'medium',
 'Cho một chuỗi ký tự chỉ gồm các chữ cái tiếng Anh không dấu. Hãy đếm số lượng nguyên âm (a, e, i, o, u - không phân biệt hoa thường) trong chuỗi.',
 'Đầu vào: Một dòng chứa chuỗi ký tự S (độ dài không quá 10^5).\nĐầu ra: Số lượng nguyên âm trong chuỗi.',
 '[{"stdin": "HelloWorld", "stdout": "3"}, {"stdin": "AEIOU", "stdout": "5"}]',
 9, 'Nhóm 11 tự biên soạn (CC0 1.0)'),

(10, 'Bài 10: Tìm phần tử lớn thứ hai', 'Mảng & Sắp xếp', 'hard',
 'Cho mảng gồm n số nguyên. Hãy tìm giá trị lớn thứ hai (khác giá trị lớn nhất) trong mảng. Nếu mọi phần tử đều bằng nhau hoặc không tồn tại giá trị lớn thứ hai, in ra -1.',
 'Đầu vào: Dòng 1 chứa số nguyên n (2 <= n <= 10^5). Dòng 2 chứa n số nguyên cách nhau bởi khoảng trắng.\nĐầu ra: Giá trị lớn thứ hai hoặc -1.',
 '[{"stdin": "5\\n1 5 3 5 2", "stdout": "3"}, {"stdin": "3\\n7 7 7", "stdout": "-1"}]',
 10, 'Tham khảo CSES Problem Set (CC BY-NC-SA 4.0)');

-- =================================================================================
-- TẠO PHIÊN BẢN ĐẦU TIÊN (v1) CHO BÀI 2 -> BÀI 10
-- =================================================================================
INSERT INTO problem_versions (id, problem_id, version_number, time_limit_ms, memory_limit_kb, created_by) VALUES
(2, 2, 1, 1000, 65536, 1),
(3, 3, 1, 1000, 65536, 1),
(4, 4, 1, 1000, 65536, 1),
(5, 5, 1, 1000, 65536, 1),
(6, 6, 1, 1000, 65536, 1),
(7, 7, 1, 1000, 65536, 1),
(8, 8, 1, 1000, 65536, 1),
(9, 9, 1, 1000, 65536, 1),
(10, 10, 1, 1000, 65536, 1);

-- =================================================================================
-- NẠP 45 CA KIỂM THỬ (5 TEST CASES / BÀI CHO BÀI 2 -> BÀI 10)
-- =================================================================================

-- Bài 2: Chẵn lẻ (2 công khai, 3 chấm kín có số 0 và số âm lớn)
INSERT INTO test_cases (problem_version_id, case_order, stdin_data, expected_stdout, is_public) VALUES
(2, 1, '4\n', 'EVEN\n', TRUE),
(2, 2, '7\n', 'ODD\n', TRUE),
(2, 3, '0\n', 'EVEN\n', FALSE),
(2, 4, '-999999999999999999\n', 'ODD\n', FALSE),
(2, 5, '-1000000000000000000\n', 'EVEN\n', FALSE);

-- Bài 3: Max 3 số
INSERT INTO test_cases (problem_version_id, case_order, stdin_data, expected_stdout, is_public) VALUES
(3, 1, '3 9 5\n', '9\n', TRUE),
(3, 2, '-5 -2 -10\n', '-2\n', TRUE),
(3, 3, '0 0 0\n', '0\n', FALSE),
(3, 4, '1000000000 999999999 1000000000\n', '1000000000\n', FALSE),
(3, 5, '-1000000000 -1000000000 -999999999\n', '-999999999\n', FALSE);

-- Bài 4: Tổng 1..N (Test số 5 có N = 10^9 kiểm tra tràn số và công thức O(1))
INSERT INTO test_cases (problem_version_id, case_order, stdin_data, expected_stdout, is_public) VALUES
(4, 1, '5\n', '15\n', TRUE),
(4, 2, '10\n', '55\n', TRUE),
(4, 3, '1\n', '1\n', FALSE),
(4, 4, '100000\n', '5000050000\n', FALSE),
(4, 5, '1000000000\n', '500000000500000000\n', FALSE);

-- Bài 5: Giai thừa N! (Có biên N = 0 và N = 20)
INSERT INTO test_cases (problem_version_id, case_order, stdin_data, expected_stdout, is_public) VALUES
(5, 1, '5\n', '120\n', TRUE),
(5, 2, '0\n', '1\n', TRUE),
(5, 3, '1\n', '1\n', FALSE),
(5, 4, '10\n', '3628800\n', FALSE),
(5, 5, '20\n', '2432902008176640000\n', FALSE);

-- Bài 6: Số nguyên tố (Có biên số <= 1, số 2 và số nguyên tố lớn)
INSERT INTO test_cases (problem_version_id, case_order, stdin_data, expected_stdout, is_public) VALUES
(6, 1, '7\n', 'YES\n', TRUE),
(6, 2, '9\n', 'NO\n', TRUE),
(6, 3, '1\n', 'NO\n', FALSE),
(6, 4, '2\n', 'YES\n', FALSE),
(6, 5, '999999937\n', 'YES\n', FALSE);

-- Bài 7: Ước chung lớn nhất GCD
INSERT INTO test_cases (problem_version_id, case_order, stdin_data, expected_stdout, is_public) VALUES
(7, 1, '12 18\n', '6\n', TRUE),
(7, 2, '17 13\n', '1\n', TRUE),
(7, 3, '100 10\n', '10\n', FALSE),
(7, 4, '1000000000000000000 500000000000000000\n', '500000000000000000\n', FALSE),
(7, 5, '999999999999999989 1\n', '1\n', FALSE);

-- Bài 8: Số Fibonacci thứ N (Có biên N = 0, N = 1 và N = 80)
INSERT INTO test_cases (problem_version_id, case_order, stdin_data, expected_stdout, is_public) VALUES
(8, 1, '6\n', '8\n', TRUE),
(8, 2, '10\n', '55\n', TRUE),
(8, 3, '0\n', '0\n', FALSE),
(8, 4, '1\n', '1\n', FALSE),
(8, 5, '80\n', '23416728348467685\n', FALSE);

-- Bài 9: Đếm nguyên âm
INSERT INTO test_cases (problem_version_id, case_order, stdin_data, expected_stdout, is_public) VALUES
(9, 1, 'HelloWorld\n', '3\n', TRUE),
(9, 2, 'AEIOU\n', '5\n', TRUE),
(9, 3, 'bcdfghjklmnpqrstvwxyz\n', '0\n', FALSE),
(9, 4, 'DaiHocDaiNamCNTT\n', '6\n', FALSE),
(9, 5, 'aAeEiIoOuU\n', '10\n', FALSE);

-- Bài 10: Phần tử lớn thứ hai
INSERT INTO test_cases (problem_version_id, case_order, stdin_data, expected_stdout, is_public) VALUES
(10, 1, '5\n1 5 3 5 2\n', '3\n', TRUE),
(10, 2, '3\n7 7 7\n', '-1\n', TRUE),
(10, 3, '2\n10 20\n', '10\n', FALSE),
(10, 4, '4\n-10 -5 -20 -5\n', '-10\n', FALSE),
(10, 5, '6\n100 99 98 100 99 97\n', '99\n', FALSE);