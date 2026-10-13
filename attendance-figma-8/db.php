<?php
// One time zone for the whole system, so every saved time uses the same clock
date_default_timezone_set('Asia/Manila');

$pdo = new PDO(
    "mysql:host=localhost;dbname=attendance;charset=utf8mb4",
    "root",
    "",
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        // Makes NOW() in MySQL use Philippine time too (needed on hosting servers that run on UTC)
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+08:00'",
    ]
);
