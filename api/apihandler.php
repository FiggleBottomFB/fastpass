<?php

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
        if(!in_array($code, $this->validCodes) && $code != ""){
            return json_encode(["status" => "error", "message" => "invalid code"]);
        }

        $this->conn->begin_transaction();

        $maxRetries = 3;

        for ($i = 0; $i < $maxRetries; $i++) {
            try{
                $product = $this->getProductInfo($productId);
                //region handle user balance
                $user = $this->getUserInfo($userId);
                if($user['balance'] < $product['price']){
                    $this->conn->rollback();
                    echo json_encode(["status" => "error", "message" => "invalid balance"]);
                    $this->writeToError($user['username'], $product['productname'], $product['price'], "invalid balance");
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
                    $this->writeToError($user['username'], $product['productname'], $product['price'], "no ticket available");
                    return;
                }
                //endregion

                //region handle add into orders
                if($code == ""){
                    $stmt = $this->conn->prepare("INSERT INTO orders (user_id, product_id, total_amount) VALUES (?, ?, ?)");
                    $stmt->bind_param("iid", $userId, $productId, $product['price']);
                    $stmt->execute();
                    $stmt = $this->conn->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
                    $stmt->bind_param("ii", $product['price'], $userId);
                    $stmt->execute();
                }
                else {
                    // $totalAmount = 0.00;
                    $stmt = $this->conn->prepare("INSERT INTO orders (user_id, product_id, total_amount) VALUES (?, ?, ?)");
                    $stmt->bind_param("iid", $userId, $productId, $product['price']);
                    $stmt->execute();
                }
                //endregion

                $this->conn->commit();
                echo json_encode(["status" => "success", "message" => "successfully bought tickets"]);

                //region send to logger file
                if (function_exists('fastcgi_finish_request')) {
                    fastcgi_finish_request();
                }
                $log = sprintf(
                    "[%s] [USER: %s] | [PRODUCT: %s] | [TOTAL: %s] | [CODE: %s]\n",
                    date('Y-m-d H:i:s'),
                    $user['username'],
                    $product['name'],
                    $product['price'],
                    $code ?: 'NONE'
                );

                $filename = __DIR__ . '/logs/' . date('Y-m-d_H-i-s') . bin2hex(random_bytes(5)) . '.log';
                file_put_contents($filename, $log, FILE_APPEND | LOCK_EX);
                //endregion

                return;
            }
            catch(Exception $e){
                $this->conn->rollback();

                if (str_contains($e->getMessage(), 'X-TRANS-REJECTED')) {
                    echo json_encode(["status" => "error", "message" => "Too many orders"]);
                    $this->writeToError($user['username'], $product['productname'], $product['price'], $e->getMessage());
                    return;
                }

                echo json_encode(["status" => "error", "message" => "Something went wrong"]);
                $this->writeToError($user['username'], $product['productname'], $product['price'], $e->getMessage());
                return;
            }
        }
    }

    function writeToError($username, $productname, $productprice, $reason){
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        $log = sprintf(
            "[%s] [USER: %s] | [PRODUCT: %s] | [TOTAL: %s] | [Reason: %s]\n",
            date('Y-m-d H:i:s'),
            $username,
            $productname,
            $productprice,
            $reason
        );
        file_put_contents(__DIR__ . '/failedorders.log', $log, FILE_APPEND | LOCK_EX);
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


                // $userName = escapeshellarg($user['username']);
                // $productName = escapeshellarg($product['name']);
                // $price = escapeshellarg($product['price']);
                // $status = escapeshellarg("success");

                // $phpPath = 'C:\\xampp\\php\\php.exe';
                // $workerPath = __DIR__ . '\\logger.php';
                // exec("\"$phpPath\" \"$workerPath\" $userName $productName $price $status > /dev/null 2>&1 &");


                // $payload = base64_encode(json_encode([
                //     'userName' => $user['username'],
                //     'productName' => $product['name'],
                //     'totalAmount' => $product['price'],
                //     "code" => $code
                // ]));
                
                // $phpPath = 'C:\\xampp\\php\\php.exe';
                // $workerPath = __DIR__ . '\\logger.php';
                // exec("\"$phpPath\" \"$workerPath\" $payload > NUL 2>&1 &");


                // $log = sprintf(
                //     "[%s] [USER: %s] | [PRODUCT: %s] | [TOTAL: %s] | [CODE: %s]\n",
                //     date('Y-m-d H:i:s'),
                //     $user['username'],
                //     $product['name'],
                //     $product['price'],
                //     $code ?: 'NONE'
                // );
                // file_put_contents(__DIR__ . '/orders.log', $log, FILE_APPEND | LOCK_EX);

                 // register_shutdown_function(function () use ($user, $product) {
                //     $logLine = sprintf(
                //         "[%s] User: %s | Product: %s | Price: %.2f | Status: %s\n",
                //         date("Y-m-d H:i:s"),
                //         $user['username'],
                //         $product['name'],
                //         $product['price'],
                //         "success"
                //     );
                //     file_put_contents(
                //         __DIR__ . DIRECTORY_SEPARATOR . 'orders.log',
                //         $logLine,
                //         FILE_APPEND | LOCK_EX
                //     );
                // });
?>