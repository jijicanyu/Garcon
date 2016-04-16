#!/usr/bin/env php
<?php
require './vendor/autoload.php';
use PhpParser\Error;
use PhpParser\ParserFactory;

$code = file_get_contents("php://stdin");
$parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);

try {
    $stmts = $parser->parse($code);
    // $stmts is an array of statement nodes
} catch (Error $e) {
    echo 'Parse Error: ', $e->getMessage();
}
//var_dump($stmts);
?>