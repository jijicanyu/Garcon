<?php
function get_title() {
    return strtolower($_GET['title']);
}

function get_rand_page() {
    return rand(0, 10);
}
$title = get_title();
$page = get_rand_page();
$sql = "SELECT * FROM articles WHERE title = '$title' AND page = $page";
mysql_query($sql);
?>