#!/usr/bin/env php
<?php
require './vendor/autoload.php';
use PhpParser\Error;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

class MyNodeVisitor extends NodeVisitorAbstract
{
    public function enterNode(Node $node) {
        if ($node instanceof Node\Expr) {
            eval_expr($node);
        }
        //var_dump($node);
        //echo get_class($node), "\n";
    }
}

$parser        = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
$traverser     = new NodeTraverser;
$prettyPrinter = new PrettyPrinter\Standard;

// add your visitor
$traverser->addVisitor(new MyNodeVisitor);

$code = file_get_contents("php://stdin");
// parse
$stmts = $parser->parse($code);

$tainted_vars = array();
$sources = array('argv'=>1);
$sinks = array("pg_query"=>1);
$funcs = array(); // a map of user defined functions
/* construct funcs dict */
foreach($stmts as $stmt) {
    if ($stmt instanceof Node\Stmt\Function_) {
        $funcs[$stmt->name] = $stmt->stmts;
    }
}
enter_call($stmts, $tainted_vars);
// var_dump($tainted_vars);
echo $code;

function do_call($func_call, $caller_table) {
    global $funcs;
    $callee_table = gen_sym_table($func_call, $caller_table);
    enter_call($funcs[$func_call->name], $callee_table);
}
    
    
function enter_call($func_stmts, $sym_table) {   
    /* only consider assign for now */
    foreach ($func_stmts as $stmt) {
        echo "process one stmt...\n";
        if ($stmt instanceof Node\Expr\Assign) {
            echo "process assign...\n";
            $istainted = eval_expr($stmt->expr, $sym_table);
            if ($istainted) {
                echo "add {$stmt->var->name}\n";
                $sym_table[$stmt->var->name] = 1;
            }
        }
        else if ($stmt instanceof Node\Expr\FuncCall) {
            echo "call:", $stmt->name, "\n";
            //do_call($stmt, $sym_table);
            
        }
        else if ($stmt instanceof Node\Stmt\Function_) {
            /* skip declare statement */
            continue;
        }
        else {
            echo "unsupported statement type ".get_class($stmt)."\n";
        }
        //$traverser->traverse(array($stmt));
    }    
}

function gen_sym_table($callee, $caller_table) {
    $newtable = array();
    var_dump($callee);
}


function check_var($name) {
    global $tainted_vars;
    echo "check var $name\n";
    if (array_key_exists($name, $tainted_vars)) {
        return true;
    }
    else {
        return false;
    }    
}

function is_sink($func) {
    global $sinks;
    if (array_key_exists($func->name->parts[0], $sinks)) {
        return true;
    }
    return false;
}

function is_args_tainted($func) {
    foreach($func->args as $arg) {
        if (eval_expr($arg)) {        
            return true;
        }
    }
    return false;
}

function is_source($name) {
    global $sources;
    if (array_key_exists($name, $sources)) {
        return true;
    }
    else {
        return false;
    }
}


function eval_expr($expr) {
    $expr_type = get_class($expr);
    if ($expr instanceof Node\Expr\Variable) {
        echo "evaluate var {$expr->name}...\n";
        if (check_var($expr->name)) {
     
            return true;
        }
        else {
            return false;
        }
    }
    else if ($expr instanceof Node\Scalar\LNumber) {

        echo "evaluate lnumber {$expr->value}...\n";
        return false;
    }
    else if ($expr instanceof Node\Scalar\String_) {
        echo "evaluate string {$expr->value}...\n";
        return false;
    }

    else if ($expr instanceof Node\Expr\ArrayDimFetch) {
        echo "evaluate arraydimfetch...\n";
        if (is_source($expr->var->name)) {
            return true;
        }
        else {
            return false;
        }
    }
    else if ($expr instanceof Node\Expr\BinaryOp) {
        echo "evaluate binaryOp $expr_type...\n";
        return eval_expr($expr->left) || eval_expr($expr->right);
    }
    else if ($expr instanceof Node\Scalar\Encapsed) {
        echo "evaluate binaryOp $expr_type...\n";
        foreach($expr->parts as $part) {
            if (eval_expr($part)) {
                return true;
            }
        }
        return false;
    }
    else if ($expr instanceof Node\Scalar\EncapsedStringPart) {
        echo "evaluate EncapsedStringPart $expr->value...\n";
        return false;
    }
    else if ($expr instanceof Node\Expr\FuncCall) {
        echo "evaluate funcCall $expr->name...\n";
        if (is_sink($expr)) {
            if (is_args_tainted($expr))
            {
                echo "SQL injection vulnerability found in line {$expr->getline()}\n";
               // throw new Exception("SQL injection vulnerability found in line {$expr->getline()}");
            }
            else {
                return false;
            }
            
        }
        else {            
            if (is_args_tainted($expr)) {
                return true;
            }
            else {
                return false;
            }
        }
        return false;
    }
    else if ($expr instanceof Node\Arg) {
        return eval_expr($expr->value);
        
    }
            
    else {
        echo "unsupported expr type: $expr_type\n";
        var_dump($expr);
    }
}
?>