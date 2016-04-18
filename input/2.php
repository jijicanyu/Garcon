<?php
function bar($foo) {
    return file_get_contents($foo);
}

$a = "bar";
$b = bar($a."1", " ");
$c[0] = $b;
//mysql_query($b);
$mysqli->query($c[0]);

?>