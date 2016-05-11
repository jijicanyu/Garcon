<?php
function modify0($a) {
    $a = "safe";
}

function modify1($a) {
    $a[0] = "safe";
}

function modify2($a) {
    $a->foo = "foo";
}

$a = $_GET['u'];
$b[0] = $_GET['u'];
$c->foo = $_GET['u'];
modify0($a);
modify1($b);
modify2($c);
system($a);
system($b[0]);
system($c->foo);