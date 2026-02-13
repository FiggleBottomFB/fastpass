<?php
while(true){
    // if(file_exists("stop")){
    //     break;
    // }
    $logsDir = __DIR__ . DIRECTORY_SEPARATOR . 'logs';
    $finalLog = __DIR__ . DIRECTORY_SEPARATOR . 'orders.log';
    $errorLog = __DIR__ . DIRECTORY_SEPARATOR . 'failedorders.log';
    $lockFile = __DIR__ . DIRECTORY_SEPARATOR . 'logger.lock';


    if (!is_dir($logsDir)) {
        exit;
    }

    $files = scandir($logsDir);
    sort($files, SORT_STRING);

    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        if (substr($file, -4) !== '.log') continue;
    
        $filePath = $logsDir . '/' . $file;
    
        if (!is_file($filePath)) continue;
    
        $content = file_get_contents($filePath);
        if ($content === false || $content === '') continue;
        
        if (strtolower(substr($file, 0, 5)) === 'error') {
            file_put_contents($errorLog, $content, FILE_APPEND | LOCK_EX);
        } else {
            file_put_contents($finalLog, $content, FILE_APPEND | LOCK_EX);
        }
    
        unlink($filePath);
    }
    usleep(500000);
}