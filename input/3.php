<?php
$user = $_GET['user'];
$users = [];
array_push($users, $user);
$query = "SELECT whatever ... " . $users[0];

}
?>