<?php
$a = $_GET['a'];
$b = strtolower($a);
$result = mysql_query("SELECT id FROM people WHERE id = '42'");
$rows = mysql_fetch_row($result);
system($b);
mysql_query($b);
print_("id: 42, name: ".$rows[0]);
print_($a);
?>