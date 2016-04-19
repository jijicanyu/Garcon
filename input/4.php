<?php

// $mysqli = new mysqli("localhost", "my_user", "my_password", "test");
// $result = $mysqli->query("SELECT DATABASE()");

$a = foo();
$b = "a";
if ($a) {
$b = $_GET[0];
}
else {
$b = "b";
}
mysql_query($b);

?>