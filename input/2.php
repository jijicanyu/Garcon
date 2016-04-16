<?php
function foo($s) {
    return $s."$argv[0]";
}

$q = foo("select whatever");

?>