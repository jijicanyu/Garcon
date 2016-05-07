<?php
use PhpParser\PrettyPrinter;
use PhpParser\Node;



$log = fopen('php://stderr','a');
$prettyPrinter = new PrettyPrinter\Standard;

function pp($msg) {
    global $log;
    if (gettype($msg) == 'array' || gettype($msg) == 'object') {
        fwrite($log, var_dump($msg).PHP_EOL);
    }
    else {
        fwrite($log, $msg.PHP_EOL);
    }
}

function deep_copy_arr($arr) {
    $newarray = [];
    foreach ($arr as $k=>$v) {
        $newarray[$k] = clone $v;
    }
    return $newarray;
}

function soft_copy_arr($arr) {
    $newarray = [];
    foreach ($arr as $k=>$v) {
        $newarray[$k] = &$v;
    }
    return $newarray;
}

function deep_copy_2d_arr($arr) {
    $new = [];
    foreach ($arr as $k=>$v) {
        $newi = [];
        foreach ($v as $vk=>$vv) {
            $newi[$vk] = clone $vv;
        }
        $new[$k] = $newi;
    }
    return $new;
}

function get_stmt_str($stmt) {
    global $prettyPrinter;
    return $prettyPrinter->prettyPrint([$stmt]);
}

function get_stmts_str($stmts) {
    global $prettyPrinter;
    return $prettyPrinter->prettyPrint($stmts);
}