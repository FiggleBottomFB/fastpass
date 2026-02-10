<?php

require "APIHandler.php";

$apiHandler = new APIHandler();

header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

$productId = $data['product_id'];
$userId = $data['user_id'];
$totalAmount = $data['total_amount'];
$code = $data["campaign_code"] ?? "";

if(!is_int($productId) || !is_int($userId) || !is_float($totalAmount) || !is_string($code)){
    echo json_encode(['status' => 'error', 'message' => 'Invalid data types']);
    exit;
}

echo $apiHandler->buyTicket($userId, $productId, $totalAmount, $code);

?>