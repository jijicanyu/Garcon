<?php
function bar($foo) {
    return file_get_contents($foo);
}

$a = "bar";
$b = bar($a."1");

pg_query($b);
?>