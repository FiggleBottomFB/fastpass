<?php
$data = json_decode(base64_decode($argv[1]), true);

$log = sprintf(
       "[%s] [USER: %s] | [PRODUCT: %s] | [TOTAL: %s]\n",
       date('Y-m-d H:i:s'),
       $data['userName'],
       $data['productName'],
       $data['totalAmount']
   );

//    $logData = [
//        'username' => $data['userName'],
//        'productname' => $data['productName'],
//        'amount' => $data['totalAmount'],
//        'time' => date('Y-m-d H:i:s')
//    ];
   
//    file_put_contents(
//        DIR . '/temp-log.json',
//        json_encode($logData) . PHP_EOL,
//        FILE_APPEND | LOCK_EX
//    );

   
file_put_contents(__DIR__ . '/orders.log', $log, FILE_APPEND | LOCK_EX);