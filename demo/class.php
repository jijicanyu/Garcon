<?php
$a->var = $_GET[0];
$b = $a;
$a = new AA();
system($b->var);
system($a->var);

$x[0] = $_GET[0];
$x[1] = "safe";
$x[foo()] = $_GET[0];
$x[bar()] = $_GET[0];
system($x[0]);
system($x[1]);
system($x[100]);