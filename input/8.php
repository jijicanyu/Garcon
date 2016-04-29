<?php
if ($a == 0) {
    $b = mysql_fetch_row($foo);
}
else if ($a == 1) {
    $b = "";
}
// else if ($a == 2) {
//     $b = "";
// }
else {
    $b = "";
}
print_($b);