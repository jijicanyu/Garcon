<?php
$b = "";
if ($c) {
    $b = $_GET[0];
}
else {
    $a = $_GET[0];
}

$d = "";
if ($c) {
    $d = $_GET[0];
}
else {
    $d = $_GET[0];
}
mysql_query($a);
mysql_query($b);
mysql_query($d);
