#!/usr/bin/env php
<?php
require './vendor/autoload.php';
use PhpParser\Error;
use PhpParser\ParserFactory;

$code = file_get_contents("php://stdin");
$parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);


    $stmts = $parser->parse($code);

var_dump($stmts);
?>