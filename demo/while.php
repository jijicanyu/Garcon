<?php
$var = "safe";
$i = 0;
while($i < 2) {

    $var = $_GET[0];
    system($var);   // should report XSS
    $i = $i + 1;
}

//$a = "safe";
//if ($b) {
//    $a = $_GET['u'];
//    system($a);
//}

//$a = $_GET['u'];
//if ($b) {
//    system($a);
//}