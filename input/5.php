<?php
$i = 1;
while (true) {
    $i = $_GET[0];
    if ($i == 1) {
        $i = "a";
    }
}
mysql_query($i);