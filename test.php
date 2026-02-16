<?php
// $response = null;

// if ($_SERVER['REQUEST_METHOD'] === 'POST') {
//     $payload = [
//         'user_id' => (int)$_POST['user_id'],
//         'product_id' => (int)$_POST['product_id'],
//         'campaign_code' => $_POST['campaign_code'] ?? ''
//     ];

//     $ch = curl_init('http://localhost:8080/fastpass/api/buyticket.php'); // ändra URL hit
//     curl_setopt_array($ch, [
//         CURLOPT_RETURNTRANSFER => true,
//         CURLOPT_POST => true,
//         CURLOPT_HTTPHEADER => [
//             'Content-Type: application/json'
//         ],
//         CURLOPT_POSTFIELDS => json_encode($payload),
//     ]);

//     $response = curl_exec($ch);
//     $curlError = curl_error($ch);
//     curl_close($ch);

//     if ($curlError) {
//         $response = json_encode([
//             'status' => 'curl_error',
//             'message' => $curlError
//         ]);
//     }
// }

$responses = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $requestCount = max(1, (int)($_POST['request_count'] ?? 1));

    $payload = [
        'user_id' => (int)$_POST['user_id'],
        'product_id' => (int)$_POST['product_id'],
        'campaign_code' => $_POST['campaign_code'] ?? ''
    ];

    $multiHandle = curl_multi_init();
    $curlHandles = [];

    for ($i = 0; $i < $requestCount; $i++) {

        $ch = curl_init('http://localhost:8080/fastpass/api/buyticket.php');

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
        ]);

        curl_multi_add_handle($multiHandle, $ch);
        $curlHandles[] = $ch;
    }

    $running = null;

    do {
        curl_multi_exec($multiHandle, $running);
        curl_multi_select($multiHandle);
    } while ($running > 0);

    foreach ($curlHandles as $index => $ch) {

        $response = curl_multi_getcontent($ch);
        $error = curl_error($ch);
        $info = curl_getinfo($ch);

        $responses[] = [
            'request' => $index + 1,
            'http_code' => $info['http_code'],
            'total_time_ms' => round($info['total_time'] * 1000, 2),
            'response' => $error ? $error : $response
        ];

        curl_multi_remove_handle($multiHandle, $ch);
        curl_close($ch);
    }

    curl_multi_close($multiHandle);
}

?>

<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <title>Test BuyTicket Endpoint</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 700px;
            margin: 40px auto;
        }
        label {
            display: block;
            margin-top: 15px;
        }
        input {
            width: 100%;
            padding: 8px;
        }
        button {
            margin-top: 20px;
            padding: 10px 20px;
            font-size: 16px;
        }
        pre {
            background: #111;
            color: #0f0;
            padding: 15px;
            margin-top: 30px;
            overflow-x: auto;
        }
    </style>
</head>
<body>

<h1>Test BuyTicket Endpoint</h1>

<form method="post">
    <label>
        User ID
        <input type="number" name="user_id" required>
    </label>

    <label>
        Product ID
        <input type="number" name="product_id" required>
    </label>

    <label>
        Campaign Code (valfri)
        <input type="text" name="campaign_code">
    </label>

    <label>
        Antal requests
        <input type="number" name="request_count" value="1" min="1" max="100">
    </label>


    <button type="submit">Skicka request</button>
</form>

<?php if (!empty($responses)): ?>
    <h2>Resultat</h2>

    <?php foreach ($responses as $r): ?>
        <h3>Request <?= $r['request'] ?></h3>
        <p><strong>HTTP-kod:</strong> <?= $r['http_code'] ?></p>
        <p><strong>Svarstid:</strong> <?= $r['total_time_ms'] ?> ms</p>
        <pre><?= htmlspecialchars($r['response']) ?></pre>
        <hr>
    <?php endforeach; ?>

<?php endif; ?>

</body>
</html>
