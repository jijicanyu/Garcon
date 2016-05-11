<?php
if(input_from_params()) {
    $a = $_GET[0];
} else {
    $a = mysql_fetch_row();
}
print_($a);
system($a);