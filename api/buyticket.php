<?php

require "APIHandler.php";

$apiHandler = new APIHandler();

header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

if(!isset($data['user_id'])){
    echo json_encode(['status' => 'error', 'message' => 'Missing parameter: user_id']);
    exit;
}
if(!isset($data['product_id'])){
    echo json_encode(['status' => 'error', 'message' => 'Missing parameter: product_id']);
    exit;
}

$productId = $data['product_id'];
$userId = $data['user_id'];
$code = $data["campaign_code"] ?? "";

if(!is_int($productId) || !is_int($userId) || !is_string($code)){
    echo json_encode(['status' => 'error', 'message' => 'Invalid data types']);
    exit;
}

$apiHandler->buyTicket($userId, $productId, $code);

?>