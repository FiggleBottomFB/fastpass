<?php
require_once 'api/apihandler.php';

$apihandler = new APIHandler();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <script>
        async function buyTicket(e){
            // e.preventDefault();
            var userid = parseInt(document.getElementsByName("userid")[0].value);
            var productid = parseInt(document.getElementsByName("productid")[0].value);
            const response = await fetch("http://localhost:8080/fastpass/api/buyticket.php", {
                method: "POST",
                headers: {"Content-Type": "application/json"},
                body: JSON.stringify({"user_id": userid, "product_id": productid, "code": ""})
            });
            const data = await response.json();
            console.log(data); 
        }
    </script>
    <form action="" method="post" onsubmit="buyTicket(event)">
        <label for="userid">Användarens id</label>
        <input type="number" name="userid" id="">
        <label for="productid">Produktens id</label>
        <input type="number" name="productid" id="">
        <button onclick="">Köp biljett</button>
    </form>
    <div id="info-container">
        <div id="user">
        <?php
            // for($i=1;$i<11;$i++){
            //     $users = $apihandler->getAllUsers($i);
            //     echo '<div class="user-container">'.
            //             '<p>'.$users["username"].'</p>'.
            //             '<p>'.$users["balance"].'</p>'.
            //         '</div>';
            // }
        ?>
        </div>
        <div id="prod">
        <?php
            // for($i=1;$i<11;$i++){
            //     $products = $apihandler->getAllProducts($i);
            //     echo '<div class="product-container">'.
            //             '<p>'.$products["name"].'</p>'.
            //             '<p>'.$products["price"].'</p>'.
            //             '<p>'.$products["stock_count"].'</p>'.
            //         '</div>';
            // }
        ?>
        </div>
    </div>

    <?php
    
    $url = "http://localhost:8080/fastpass/api/buyticket.php";

    $data = [
        "user_id" => 2,
        "product_id"  => 2
    ];

    $payload = json_encode($data);

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "Content-Length: " . strlen($payload)
        ],
        CURLOPT_POSTFIELDS => $payload
    ]);

    $response = curl_exec($ch);

    echo $response;
    ?>


    <button onclick="spamBuyTickets()">Skicka massor av requests</button>

<script>
var minurl = "http://localhost:8080/fastpass/api/buyticket.php";
var arvidurl = "http://192.168.218.59:8080/Transaxtions LPS AB/Transaxtions-LPS-AB/php/buy.php";
async function buyTicket(userid, productid) {
    const response = await fetch(minurl, {
        method: "POST",
        headers: {"Content-Type": "application/json"},
        body: JSON.stringify({"user_id": userid, "product_id": 10, "campaign_code": ""})
    });

    return response.json();
}

async function spamBuyTickets() {
    const NUMBER_OF_REQUESTS = 100;

    console.log("Startar stress-test...");

    const promises = [];

    for (let i = 0; i < NUMBER_OF_REQUESTS; i++) {
        const randomUserId = Math.floor(Math.random() * 10) + 1;
        const randomProductId = Math.floor(Math.random() * 10) + 1;
        promises.push(buyTicket(randomUserId, randomProductId));
    }

    try {
        const results = await Promise.all(promises);
        console.log("Alla requests klara:", results);
    } catch (err) {
        console.error("Fel vid requests:", err);
    }
}
</script>

</body>
</html>