<?php

/* expr:
   ArrayDimFetch
   BinaryOp
   Assign
   FuncCall
   Variable
   LNumber
   String_
*/

$offset = $argv[0]; // beware, no input validation!
$query  = "SELECT id, name FROM products ORDER BY name LIMIT 20 OFFSET $offset;";
$query1 = "SELECT ...";
//$query = $query1;
// return type is not string
$foolist = sqlit($query, " ");
// no return type
string_routine($query);
$query2 = add_num_to_str($query);
$result = pg_query($conn, $query2);



// $b = $argv[0];
// $a = (1+2-1)."$b bar";
// $c = substr(strtolower($a)."lower", 1);
// pg_query($c);
//pg_query(f2(($a+1*2)."some"));



?>