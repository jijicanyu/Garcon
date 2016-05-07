<?php
require_once './vendor/autoload.php';
require_once 'symbolTable.php';
require_once 'taintInfo.php';
require_once 'utility.php';

$a = new Condition();
$a->setExpr("$a");
$a->setValue(false);
$aa = new CompoundCondition($a);

$a1 = new Condition();
$a1->setExpr("$a");
$a1->setValue(false);

$aa->ConcatCondition($a1, "and");

echo $aa->toString();
//$aa = new CompoundCondition($a);

?>