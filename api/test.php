<?php

$host = "localhost";
$db   = "fastpass";
$user = "root";
$pass = "";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$stmt = $conn->prepare("SELECT * FROM products");
$stmt->execute();

$result = $stmt->get_result();
$products = $result->fetch_all();

print_r($products);
