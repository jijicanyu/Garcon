<?php

$log = fopen('php://stderr','a');
function pp($msg) {
    global $log;
    if (gettype($msg) == 'array' || gettype($msg) == 'object') {
        fwrite($log, var_dump($msg).PHP_EOL);
    }
    else {
        fwrite($log, $msg.PHP_EOL);
    }
}