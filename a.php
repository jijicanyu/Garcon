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

$tainted_vars = array();
$sources = array('argv'=>1);
$sinks = array("pg_query"=>1);

foreach ($stmts as $stmt) {    
    if (get_class($stmt) == "PhpParser\Node\Expr\Assign") {
        $left = $stmt->var;
        $right = $stmt->expr;
        echo "right:", get_class($right), "\n";
        
        if (check_tainted($right)) {
            $tainted_vars[$left->name] = 1;
        }
    }
    else if (get_class($stmt) == "PhpParser\Node\Expr\FuncCall") {
        if (check_sink($stmt)) {
            echo "check sink...\n";
        }
    }
    // else
    
}
var_dump($tainted_vars);

function check_sink($func) {
    global $sinks;
    if (array_key_exists($func->name->parts[0], $sinks)) {
        foreach($func->args as $arg) {
            if (check_var($arg->value->name)) {
                return true;
            }
        }
    }
    return false;
}

function check_source($name) {
    global $sources;
    if (array_key_exists($name, $sources)) {
        return true;
    }
    else {
        return false;
    }
}

function check_var($name) {
    global $tainted_vars;
    if (array_key_exists($name, $tainted_vars)) {
        return true;
    }
    else {
        return false;
    }
    
}

function check_tainted($node)
{
    if (get_class($node) == "PhpParser\Node\Expr\ArrayDimFetch") {
        $array = $node->var;
        $dim0 = $node->dim;

        if (check_source($array->name)) {
            return true;
        }
        else {
            return false;
        }
    }
    else if (get_class($node) == "PhpParser\Node\Scalar\String_") {
        return false;
    }
    else if (get_class($node) == "PhpParser\Node\Scalar\Encapsed") {
        /* check if contains any tainted string variable */
        foreach($node->parts as $eachpart) {
            echo get_class($eachpart);
            if (get_class($eachpart) == "PhpParser\Node\Expr\Variable" 
                && check_var($eachpart->name)) {
                echo "string contains tainted var\n";
                return true;
            }
        }
        return false;
        
    }
    else if (get_class($node) == "PhpParser\Node\Expr\Variable") {
        if (check_var($node->name)) {
            return true;
        }
        else {
            return false;
        }
    }
    else if (get_class($node) == "PhpParser\Node\Expr\FuncCall") {
        $lineno = $node->getLine();
        if (check_sink($node)) {
            throw new Exception("SQL injection vulnerability found in line $lineno");
        }
        else {
            call_func($node);
        }
    }
    // other check

    //
    return false; 
}

function call_func($node) {
    //var_dump($node);
}
?>