<?php
$a = $_GET[0];
$a = htmlspecialchars($pattern, $rep, $a);
mysql_query($a);