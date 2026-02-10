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


    function BuyTicket($userId, $productId, $totalAmount, $code){
        if(!in_array($code, $this->validCodes) && $code != ""){
            return json_encode(["status" => "error", "message" => "invalid code"]);
        }

        $this->conn->begin_transaction();

        $maxRetries = 3;

        for ($i = 0; $i < $maxRetries; $i++) {
            try{
                //region handle user balance
                $balance = $this->getUserBalance($userId);
                if($balance < $totalAmount){
                    $this->conn->rollback();
                    return json_encode(["status" => "error", "message" => "invalid balance"]);
                }
                //endregion

                //region handle stock count update
                $stmt = $this->conn->prepare("UPDATE products SET stock_count = stock_count - 1 WHERE id = ? AND stock_count > 0");
                $stmt->bind_param("i", $productId);
                $stmt->execute();

                if($stmt->affected_rows !== 1){
                    $this->conn->rollback();
                    return json_encode(["status" => "error", "message" => "no ticket available"]);
                }
                //endregion

                //region handle add into orders
                if($code == ""){
                    $stmt = $this->conn->prepare("INSERT INTO orders (user_id, product_id, total_amount) VALUES (?, ?, ?)");
                    $stmt->bind_param("iid", $userId, $productId, $totalAmount);
                    $stmt->execute();
                    $stmt = $this->conn->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
                    $stmt->bind_param("ii", $totalAmount, $userId);
                    $stmt->execute();
                }
                else {
                    // $totalAmount = 0.00;
                    $stmt = $this->conn->prepare("INSERT INTO orders (user_id, product_id, total_amount) VALUES (?, ?, ?)");
                    $stmt->bind_param("iid", $userId, $productId, $totalAmount);
                    $stmt->execute();
                }
                //endregion

                $this->conn->commit();
                return json_encode(["status" => "success", "message" => "successfully bought tickets"]);
            }
            catch(Exception $e){
                $this->conn->rollback();
                throw $e;
            }
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
}

?>