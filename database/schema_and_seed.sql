CREATE DATABASE IF NOT EXISTS online_judge_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE online_judge_db;

-- Bảng người dùng (Người học, Giảng viên, Quản trị viên dịch vụ thực thi)
CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('student', 'teacher', 'admin') NOT NULL DEFAULT 'student',
    class_code VARCHAR(50) DEFAULT 'CNTT18-11',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 1. Bảng bài tập (problems)
CREATE TABLE IF NOT EXISTS problems (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    topic VARCHAR(100) NOT NULL,
    difficulty ENUM('easy', 'medium', 'hard') NOT NULL DEFAULT 'easy',
    description TEXT NOT NULL,
    io_description TEXT NOT NULL,
    public_examples JSON NOT NULL,
    current_version_id BIGINT UNSIGNED NULL,
    source_credit VARCHAR(255) NOT NULL DEFAULT 'Tự biên soạn (CC0 1.0)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Bảng phiên bản bộ dữ liệu chấm (problem_versions)
CREATE TABLE IF NOT EXISTS problem_versions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    problem_id BIGINT UNSIGNED NOT NULL,
    version_number INT UNSIGNED NOT NULL,
    time_limit_ms INT UNSIGNED NOT NULL DEFAULT 1000,
    memory_limit_kb INT UNSIGNED NOT NULL DEFAULT 65536,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_problem_ver (problem_id, version_number),
    FOREIGN KEY (problem_id) REFERENCES problems(id) ON DELETE CASCADE
);

-- 3. Bảng các ca kiểm thử (test_cases)
CREATE TABLE IF NOT EXISTS test_cases (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    problem_version_id BIGINT UNSIGNED NOT NULL,
    case_order INT UNSIGNED NOT NULL,
    stdin_data LONGTEXT NOT NULL,
    expected_stdout LONGTEXT NOT NULL,
    is_public BOOLEAN NOT NULL DEFAULT FALSE,
    FOREIGN KEY (problem_version_id) REFERENCES problem_versions(id) ON DELETE CASCADE
);

-- Bảng lưu bản nháp trên trình duyệt (Chức năng 2)
CREATE TABLE IF NOT EXISTS code_drafts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    problem_id BIGINT UNSIGNED NOT NULL,
    language ENUM('python', 'cpp') NOT NULL DEFAULT 'python',
    source_code LONGTEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_problem_lang (user_id, problem_id, language)
);

-- 4. Bảng bài nộp (submissions)
CREATE TABLE IF NOT EXISTS submissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    problem_id BIGINT UNSIGNED NOT NULL,
    initial_version_id BIGINT UNSIGNED NOT NULL,
    language ENUM('python', 'cpp') NOT NULL,
    source_code LONGTEXT NOT NULL,
    idempotency_key VARCHAR(64) NOT NULL,
    latest_run_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_idempotency (user_id, idempotency_key),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (problem_id) REFERENCES problems(id),
    FOREIGN KEY (initial_version_id) REFERENCES problem_versions(id)
);

-- 5. Bảng các lần thực thi chấm (judging_runs)
CREATE TABLE IF NOT EXISTS judging_runs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    submission_id BIGINT UNSIGNED NOT NULL,
    problem_version_id BIGINT UNSIGNED NOT NULL,
    is_rejudge BOOLEAN NOT NULL DEFAULT FALSE,
    status ENUM('queued', 'running', 'AC', 'WA', 'TLE', 'RE', 'CE', 'SYSTEM_ERROR') DEFAULT 'queued',
    passed_cases INT UNSIGNED DEFAULT 0,
    total_cases INT UNSIGNED DEFAULT 0,
    started_at TIMESTAMP NULL,
    finished_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE,
    FOREIGN KEY (problem_version_id) REFERENCES problem_versions(id)
);

-- 6. Bảng kết quả từng ca kiểm thử (case_results)
CREATE TABLE IF NOT EXISTS case_results (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    judging_run_id BIGINT UNSIGNED NOT NULL,
    test_case_id BIGINT UNSIGNED NOT NULL,
    status ENUM('AC', 'WA', 'TLE', 'RE', 'CE', 'SYSTEM_ERROR') NOT NULL,
    execution_time_ms INT UNSIGNED DEFAULT 0,
    actual_stdout LONGTEXT NULL,
    error_message TEXT NULL,
    FOREIGN KEY (judging_run_id) REFERENCES judging_runs(id) ON DELETE CASCADE,
    FOREIGN KEY (test_case_id) REFERENCES test_cases(id)
);

-- NẠP TÀI KHOẢN MẪU (Mật khẩu mặc định: 123456)
INSERT INTO users (id, username, password_hash, full_name, role, class_code) VALUES
(1, 'teacher1', '123456', 'Giảng viên Quản lý đề', 'teacher', 'CNTT18-11'),
(2, 'studentA', '123456', 'Người học A', 'student', 'CNTT18-11'),
(3, 'studentB', '123456', 'Người học B', 'student', 'CNTT18-11'),
(4, 'admin1',   '123456', 'Quản trị dịch vụ thực thi', 'admin', 'CNTT18-11');

-- NẠP BÀI TẬP MẪU SỐ 1 (Tổng hai số nguyên A và B - Kèm 5 ca kiểm thử có dữ liệu biên)
INSERT INTO problems (id, title, topic, difficulty, description, io_description, public_examples, current_version_id, source_credit) VALUES
(1, 'Bài 1: Tính tổng hai số nguyên', 'Cơ bản', 'easy',
 'Cho hai số nguyên a và b. Hãy viết chương trình tính và in ra tổng của hai số đó.',
 'Đầu vào: Một dòng duy nhất chứa 2 số nguyên a và b cách nhau bởi khoảng trắng (-10^9 <= a, b <= 10^9).\nĐầu ra: Một số nguyên duy nhất là tổng a + b.',
 '[{"stdin": "3 5", "stdout": "8"}, {"stdin": "-10 10", "stdout": "0"}]',
 1, 'Nhóm 11 tự biên soạn (CC0 1.0)');

INSERT INTO problem_versions (id, problem_id, version_number, time_limit_ms, memory_limit_kb, created_by) VALUES
(1, 1, 1, 1000, 65536, 1);

INSERT INTO test_cases (problem_version_id, case_order, stdin_data, expected_stdout, is_public) VALUES
(1, 1, '3 5\n', '8\n', TRUE),
(1, 2, '-10 10\n', '0\n', TRUE),
(1, 3, '0 0\n', '0\n', FALSE),
(1, 4, '1000000000 1000000000\n', '2000000000\n', FALSE),
(1, 5, '-1000000000 -1000000000\n', '-2000000000\n', FALSE);