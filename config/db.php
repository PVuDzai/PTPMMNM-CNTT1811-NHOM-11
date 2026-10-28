<?php
// config/db.php - Kết nối PDO tới MySQL trên XAMPP
function getDBConnection(): PDO {
    $host = '127.0.0.1';
    $port = '3306';
    $dbname = 'online_judge_db';
    $username = 'root';
    $password = ''; // Mặc định XAMPP để trống

    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    return new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}