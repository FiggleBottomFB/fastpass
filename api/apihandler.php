<?php

Class APIHandler{
    private mysqli $conn;
    private $validCodes = ["FREEBIE"];

    function __construct() {
        $host = "localhost";
        $db   = "fastpass";
        $user = "root";
        $pass = "";

        $this->conn = new mysqli($host, $user, $pass, $db);
        if ($this->conn->connect_error) {
            die("Connection failed: " . $this->conn->connect_error);
        }
    }


    function BuyTicket($userId, $productId, $code){
        if(!in_array($code, $this->validCodes) && $code != ""){
            return json_encode(["status" => "error", "message" => "invalid code"]);
        }

        $this->conn->begin_transaction();

        $maxRetries = 3;

        for ($i = 0; $i < $maxRetries; $i++) {
            try{
                $price = $this->getProductPrice($productId);
                $loggerinfo = $this->getLoggerInfo($userId, $productId);
                //region handle user balance
                $balance = $this->getUserBalance($userId);
                if($balance < $price){
                    $this->conn->rollback();
                    echo json_encode(["status" => "error", "message" => "invalid balance"]);
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
                    return;
                }
                //endregion

                //region handle add into orders
                if($code == ""){
                    $stmt = $this->conn->prepare("INSERT INTO orders (user_id, product_id, total_amount) VALUES (?, ?, ?)");
                    $stmt->bind_param("iid", $userId, $productId, $price);
                    $stmt->execute();
                    $stmt = $this->conn->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
                    $stmt->bind_param("ii", $price, $userId);
                    $stmt->execute();
                }
                else {
                    // $totalAmount = 0.00;
                    $stmt = $this->conn->prepare("INSERT INTO orders (user_id, product_id, total_amount) VALUES (?, ?, ?)");
                    $stmt->bind_param("iid", $userId, $productId, $price);
                    $stmt->execute();
                }
                //endregion

                $this->conn->commit();
                echo json_encode(["status" => "success", "message" => "successfully bought tickets"]);

                //region send to logger file
                if (function_exists('fastcgi_finish_request')) {
                    fastcgi_finish_request();
                }
                $payload = base64_encode(json_encode([
                    'userName' => $loggerinfo[0],
                    'productName' => $loggerinfo[1],
                    'totalAmount' => $price
                ]));
                
                $phpPath = 'C:\\xampp\\php\\php.exe';
                $workerPath = __DIR__ . '\\logger.php';
                exec("\"$phpPath\" \"$workerPath\" $payload > NUL 2>&1 &");
                //endregion

                return;
            }
            catch(Exception $e){
                $this->conn->rollback();

                if (str_contains($e->getMessage(), 'X-TRANS-REJECTED')) {
                    echo json_encode(["status" => "error","message" => "Too many orders"]);
                    return;
                }

                echo json_encode(["status" => "error","message" => "Something went wrong"]);
                return;
            }
        }
    }

    function getLoggerInfo($userId, $productId){
        try{
            $stmt = $this->conn->prepare("SELECT username FROM users WHERE id = ? FOR UPDATE");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $username = $result->fetch_assoc();

            $stmt = $this->conn->prepare("SELECT name FROM products WHERE id = ? FOR UPDATE");
            $stmt->bind_param("i", $productId);
            $stmt->execute();
            $result = $stmt->get_result();
            $productname = $result->fetch_assoc();

            return [$username["username"], $productname["name"]];
        }
        catch(Exception $e){
            throw $e;
        }
    }
    function getProductPrice($productId){
        try{
            $stmt = $this->conn->prepare("SELECT price FROM products WHERE id = ? FOR UPDATE");
            $stmt->bind_param("i", $productId);
            $stmt->execute();

            $result = $stmt->get_result();
            $price = $result->fetch_assoc();

            if($result->num_rows === 0){
                throw new Exception("Product not found");
            }

            return $price["price"];
        }
        catch(Exception $e){
            throw $e;
        }
    }
    function getUserBalance($userId){
        try{
            $stmt = $this->conn->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE");
            $stmt->bind_param("i", $userId);
            $stmt->execute();

            $result = $stmt->get_result();
            $balance = $result->fetch_assoc();

            if($result->num_rows === 0){
                throw new Exception("User not found");
            }

            return $balance["balance"];
        }
        catch(Exception $e){
            throw $e;
        }
    }
    function getAllUsers($userId){
        try{
            $stmt = $this->conn->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $users = $result->fetch_assoc();
            return $users;
        }
        catch(Exception $e){
            throw $e;
        }
    }
    function getAllProducts($productId){
        try{
            $stmt = $this->conn->prepare("SELECT * FROM products WHERE id = ?");
            $stmt->bind_param("i", $productId);
            $stmt->execute();
            $result = $stmt->get_result();
            $products = $result->fetch_assoc();
            return $products;
        }
        catch(Exception $e){
            throw $e;
        }
    }
}

?>