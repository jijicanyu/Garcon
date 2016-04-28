<?php
$a = $c = "good";
$b = &$a;
$a = $_GET[0];
//$a = "good";
mysql_query($b);
?>