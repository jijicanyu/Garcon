<?php
$user = $_GET['user'];
$users = array();
array_push($users, $user);
//$users[0] = $user;
$query = "SELECT whatever ... " . $users[0];
mysql_query($query, $a);
?>