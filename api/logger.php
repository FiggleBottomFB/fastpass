<?php
// $data = json_decode(base64_decode($argv[1]), true);

// $log = sprintf(
//     "[%s] [USER: %s] | [PRODUCT: %s] | [TOTAL: %s] | [CODE: %s]\n",
//     date('Y-m-d H:i:s'),
//     $data['userName'],
//     $data['productName'],
//     $data['totalAmount'],
//     $code ?: 'NONE'
// );

   
// file_put_contents(__DIR__ . '/orders.log', $log, FILE_APPEND | LOCK_EX);


// if ($argc !== 5) exit("Usage: php log_order.php <user> <product> <price> <status>\n");

// $user = $argv[1];
// $product = $argv[2];
// $price = $argv[3];
// $status = $argv[4];

// $logLine = sprintf("[%s] User: %s | Product: %s | Price: %.2f | Status: %s\n",
//     date("Y-m-d H:i:s"),
//     $user,
//     $product,
//     $price,
//     $status
// );

// file_put_contents(__DIR__ . '\orders.log', $logLine, FILE_APPEND | LOCK_EX);

while(true){
    // if(file_exists("stop")){
    //     break;
    // }
    $logsDir = __DIR__ . '/logs';
    $finalLog = __DIR__ . '/orders.log';
    $lockFile = __DIR__ . '/logger.lock';

    $lock = fopen($lockFile, 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
        exit;
    }

    if (!is_dir($logsDir)) {
        exit;
    }

    $files = scandir($logsDir);
    sort($files, SORT_STRING);

    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        if (!str_ends_with($file, '.log')) continue;

        $filePath = $logsDir . '/' . $file;
        if (!is_file($filePath)) continue;

        $content = file_get_contents($filePath);
        if ($content === false || $content === '') continue;

        file_put_contents($finalLog, $content, FILE_APPEND | LOCK_EX);
        unlink($filePath);
    }
    usleep(500000);
}