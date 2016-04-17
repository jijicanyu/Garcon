<?php
function bar($foo) {
    return file_get_contents($foo);
}

$a = "bar";
$b = bar($a."1", " ");
//$b = $a;
//mysql_query($b);
$mysqli->query($b);

?>