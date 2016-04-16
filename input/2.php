<?php
function bar($foo) {
    return file_get_contents($foo);
}

$a = "bar";
$b = bar($a."1");
$b = $a;
pg_query($b);
?>