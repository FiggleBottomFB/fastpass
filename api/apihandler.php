<?php

// Enable CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}


Class APIHandler{
    private mysqli $conn;
    private $validCodes = ["FREEBIE"];

    function __construct() {
        $host = "localhost";
        $db   = "fastpassnew";
        $user = "root";
        $pass = "";

        $this->conn = new mysqli($host, $user, $pass, $db);
        if ($this->conn->connect_error) {
            die("Connection failed: " . $this->conn->connect_error);
        }
    }


    function BuyTicket($userId, $productId, $code){

        $this->conn->begin_transaction();

        $maxRetries = 3;

        for ($i = 0; $i < $maxRetries; $i++) {
            try{
                $product = $this->getProductInfo($productId);
                $user = $this->getUserInfo($userId);

                //region handle campaign code
                if(!in_array($code, $this->validCodes) && $code != ""){
                    $this->conn->rollback();
                    echo json_encode(["status" => "error", "message" => "invalid code"]);
                    $this->writeToError($user['username'], $product['name'], $product['price'], "invalid code", $userId, $productId);
                    return;
                }
                //endregion

                //region handle user balance
                if($user['balance'] < $product['price']){
                    $this->conn->rollback();
                    echo json_encode(["status" => "error", "message" => "invalid balance"]);
                    $this->writeToError($user['username'], $product['name'], $product['price'], "invalid balance", $userId, $productId);
                    return;
                }
                //endregion

                //region handle stock count update
                $stmt = $this->conn->prepare("UPDATE products SET stock_count = stock_count - 1 WHERE id = ? AND stock_count > 0");
                $stmt->bind_param("i", $productId);
                $stmt->execute();

                if($stmt->affected_rows !== 1){
                    $this->conn->rollback();
                    echo json_encode(["status" => "error", "message" => "no ticket available"]);
                    $this->writeToError($user['username'], $product['name'], $product['price'], "no ticket available", $userId, $productId);
                    return;
                }
                //endregion

                //region handle add into orders
                if($code == ""){
                    $stmt = $this->conn->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
                    $stmt->bind_param("ii", $product['price'], $userId);
                    $stmt->execute();
                    $stmt = $this->conn->prepare("INSERT INTO orders (user_id, product_id, total_amount) VALUES (?, ?, ?)");
                    $stmt->bind_param("iid", $userId, $productId, $product['price']);
                    $stmt->execute();
                }
                else {
                    $stmt = $this->conn->prepare("INSERT INTO orders (user_id, product_id, total_amount) VALUES (?, ?, ?)");
                    $stmt->bind_param("iid", $userId, $productId, $product['price']);
                    $stmt->execute();
                }
                //endregion
                $id = (int) $this->conn->insert_id;

                $this->conn->commit();
                echo json_encode(["status" => "success", "message" => "successfully bought tickets", "charged" => !in_array($code, $this->validCodes) ? $product['price'] : 0.00]);

                //region send to logger file
                if (function_exists('fastcgi_finish_request')) {
                    fastcgi_finish_request();
                }
                $log = sprintf(
                    "[%s] | [ORDER ID: %s] | [USER ID: %s] | [USER: %s] | [PRODUCT ID: %s] | [PRODUCT: %s] | [TOTAL: %s] | [CODE: %s]\n",
                    date('Y-m-d H:i:s'),
                    $id,
                    $userId,
                    $user['username'],
                    $productId,
                    $product['name'],
                    !in_array($code, $this->validCodes) ? $product['price'] : 0.00,
                    $code ?: 'NONE'
                );
                //date('Y-m-d_H-i-s') . bin2hex(random_bytes(5))
                $filename = __DIR__ . '/logs/' . $id . '.log';
                file_put_contents($filename, $log, FILE_APPEND | LOCK_EX);
                //endregion

                return;
            }
            catch(Exception $e){
                $this->conn->rollback();
                if(!isset($product)){
                    echo json_encode(["status" => "error", "message" => "Product not found"]);
                    $this->writeToError(reason: "Product not found", productId: $productId, code: $code);
                    return;
                }
                if(!isset($user)){
                    echo json_encode(["status" => "error", "message" => "User not found"]);
                    $this->writeToError(reason: "User not found", userId: $userId, code: $code);
                    return;
                }

                if (str_contains($e->getMessage(), 'X-TRANS-REJECTED')) {
                    echo json_encode(["status" => "error", "message" => "Too many orders"]);
                    $this->writeToError($user['username'], $product['name'], $product['price'], $e->getMessage(), $userId, $productId, $code);
                    return;
                }

                echo json_encode(["status" => "error", "message" => "Something went wrong"]);
                $this->writeToError($user['username'], $product['name'], $product['price'], $e->getMessage(), $userId, $productId, $code);
                return;
            }
        }
    }

    function writeToError($username = "", $productname = "", $productprice = "", $reason = "", $userId = "", $productId = "", $code = ""){
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        $log = sprintf(
            "[%s] | [USER ID: %s] | [USER: %s] | [PRODUCT ID: %s] | [PRODUCT: %s] | [TOTAL: %s] | [CODE: %s] | [Reason: %s]\n",
            date('Y-m-d H:i:s'),
            $userId,
            $username,
            $productId,
            $productname,
            $productprice,
            $code,
            $reason
        );
        $filename = __DIR__ . '/logs/' . "error" . date('Y-m-d_H-i-s') . bin2hex(random_bytes(5)) . '.log';
        file_put_contents($filename, $log, FILE_APPEND | LOCK_EX);
    }
    function getProductInfo($productId){
        try{
            $stmt = $this->conn->prepare("SELECT price, name FROM products WHERE id = ? FOR UPDATE");
            $stmt->bind_param("i", $productId);
            $stmt->execute();

            $result = $stmt->get_result();
            $price = $result->fetch_assoc();

            if($result->num_rows === 0){
                throw new Exception("Product not found");
            }
            return $price;
            
        }
        catch(Exception $e){
            throw $e;
        }
    }
    function getUserInfo($userId){
        try{
            $stmt = $this->conn->prepare("SELECT balance, username FROM users WHERE id = ? FOR UPDATE");
            $stmt->bind_param("i", $userId);
            $stmt->execute();

            $result = $stmt->get_result();
            $balance = $result->fetch_assoc();

            if($result->num_rows === 0){
                throw new Exception("User not found");
            }

            return $balance;
        }
        catch(Exception $e){
            throw $e;
        }
    }
}
?>