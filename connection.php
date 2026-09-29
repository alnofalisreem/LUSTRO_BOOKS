<?php
$servername = 'localhost';
$username = 'root';
$password = '';
$dbname = 'lustro_books';
$port = 3306;

$conn = new mysqli($servername, $username, $password, $dbname, $port);

if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

$conn->set_charset('utf8mb4');
