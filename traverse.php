#!/usr/bin/env php
<?php
require './vendor/autoload.php';
use PhpParser\Error;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

$parser        = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
$traverser     = new NodeTraverser;
$prettyPrinter = new PrettyPrinter\Standard;

$log = fopen('php://stderr','a');
function pp($msg) {
    global $log;
    if (gettype($msg) == 'array' || gettype($msg) == 'object') {
        fwrite($log, var_dump($msg).PHP_EOL);
    }
    else {
        fwrite($log, $msg.PHP_EOL);
    }
    
}
$code = file_get_contents("php://stdin");
// parse
$stmts = $parser->parse($code);

$tainted_vars = [];

$sources['array'] = ['_GET'=>1, '_POST'=>1, '_COOKIE'=>1, '_ENV'=>1];
$sources['func'] = ['file_get_contents'=>1, 'mysql_fetch_row'=>1];
$sources['method'] = ['mysqli::query'=>1];


$sinks['sql'] = ['pg_query'=>1, 'mysql_query'=>1, 'mysqli::query'=>1];
$sinks['cmd'] = ['system'=>1];



$sql_sinks = ['pg_query'=>1];
$cmdl_sinks = ['system'=>1];
$user_funcs = []; // a map of user defined functions
/* construct funcs dict */
foreach($stmts as $stmt) {
    if ($stmt instanceof Node\Stmt\Function_) {
        $user_funcs[$stmt->name] = $stmt;
    }
}
enter_call($stmts, $tainted_vars);
pp($code);
pp($tainted_vars);
    
function enter_call($func_stmts, $sym_table) {   
    /* only consider assign for now */
    foreach ($func_stmts as $stmt) {
        if ($stmt instanceof Node\Expr\Assign) {
            pp("process assign...");            
            $istainted = eval_expr($stmt->expr, $sym_table);
            if ($istainted > 0) {
                pp("add {$stmt->var->name}");
                $sym_table[$stmt->var->name] = 1;
            }
            else if (check_var($stmt->var->name, $sym_table)) {
                /* delete entry in sym_table */
                unset($sym_table[$stmt->var->name]);
                pp("unset {$stmt->var->name}");
            }
            else {
                /* pass */                
            }
        }
        else if ($stmt instanceof Node\Expr) {
            pp("process call $stmt->name");
            eval_expr($stmt, $sym_table);
            //do_call($stmt, $sym_table);
            
        }
        else if ($stmt instanceof Node\Stmt\Function_) {
            pp("process function declaration");
            /* skip declare statement */
            continue;
        }
        else if ($stmt instanceof Node\Stmt\Return_) {
            pp("process return");
            return eval_expr($stmt->expr, $sym_table);
        }
        else {
            //pp("process unsupported statement");
            echo "unsupported statement type ".get_class($stmt)."\n";
        }
        
        //$traverser->traverse(array($stmt));
    }
    return false;
}

function gen_sym_table($args, $func_proto, $caller_table) {
    $newtable = [];
    assert(count($func_proto->params) == count($args), "parameters and arguments should have same numbers");
    for ($i = 0; $i < count($func_proto->params); $i++) {
        $params = $func_proto->params;
        $param = $params[$i];
        $newtable[$param->name] = eval_expr($args[$i], $caller_table);
    }
    return $newtable;
}

function check_var($name, $sym_table) {
    pp("check var $name");
    if (array_key_exists($name, $sym_table)) {
        return $sym_table[$name];
    }
    else {
        return 0;
    }    
}

function is_sink($func_name) {
    global $sinks;
    if (array_key_exists($func_name, $sinks['sql'])) {
        return 1;
    }
    else if (array_key_exists($func_name, $sinks['cmd'])) {
        return 2;
    }
    else {
        return false;
    }
}

function is_args_tainted($args, $sym_table) {
    foreach($args as $arg) {
        $taint_type = eval_expr($arg, $sym_table);
        if ($taint_type != 0) {        
            return $taint_type;
        }
    }
    return 0;
}

function is_source($name) {
    global $sources;
    if (array_key_exists($name, $sources['array'])) {
        return 1;
    }
    else if (array_key_exists($name, $sources['func'])) {
        return 2;
    }
    if (array_key_exists($name, $sources['method'])) {
        return 4;
    }
    else {
        return 0;
    }
}

function is_source_array($name) {
    global $sources;
    if (array_key_exists($name, $sources['array'])) {
        return true;
    }
    else {
        return false;
    }
}

function is_source_func($name) {
    global $sources;
    if (array_key_exists($name, $sources['func'])) {
        return true;
    }
    else {
        return false;
    }
}

function eval_func($func_name, $args, $sym_table) {
    global $user_funcs;
    $source_type = is_source($func_name);
    $sink_type = is_sink($func_name);
    if ($sink_type != 0) {
        if (is_args_tainted($args, $sym_table))
        {
            return -$sink_type;
        }
        return 0;
        
    }
    else if ($source_type != 0) {
        return $source_type;
        
    }
    /* if user defined function */
    else if (array_key_exists($func_name, $user_funcs)) {
        $func_proto = $user_funcs[$func_name];
        $callee_table = gen_sym_table($args, $func_proto, $sym_table);
        return enter_call($func_proto->stmts, $callee_table);        
    }
    /* if built-in function */
    else {
        return is_args_tainted($args, $sym_table);
    }
}

function eval_expr($expr, $sym_table) {
    $expr_type = get_class($expr);

    if ($expr instanceof Node\Expr\Variable) {
        pp("evaluate var {$expr->name}...");
        $taint_type = check_var($expr->name, $sym_table);
        if ($taint_type != 0) {
            $expr->setAttribute("tainted", $taint_type);
        }
        else {
            $expr->setAttribute("tainted", 0);
        }
    }
    else if ($expr instanceof Node\Scalar\LNumber) {
        pp("evaluate lnumber {$expr->value}...");
        $expr->setAttribute("tainted", 0);
    }
    else if ($expr instanceof Node\Scalar\String_) {
        pp("evaluate string {$expr->value}...");
        $expr->setAttribute("tainted", 0);
    }

    // else if ($expr instanceof Node\Expr\ArrayDimFetch) {
    //     pp("evaluate arraydimfetch...");
    //     if (is_source_array($expr->var->name)) {
    //         return true;
    //     }
    //     else {
    //         return false;
    //     }
    // }
    else if ($expr instanceof Node\Expr\BinaryOp) {
        pp("evaluate binaryOp $expr_type...");
        $is_tainted = eval_expr($expr->left, $sym_table) || eval_expr($expr->right, $sym_table);
        $expr->setAttribute("tainted", $is_tainted);
    }
    else if ($expr instanceof Node\Scalar\Encapsed) {
        pp("evaluate binaryOp $expr_type...");
        foreach($expr->parts as $part) {
            if (eval_expr($part, $sym_table)) {
                $expr->setAttribute("tainted", true);
                break;
            }
        }
        $expr->setAttribute("tainted", 0);
    }
    else if ($expr instanceof Node\Scalar\EncapsedStringPart) {
        pp("evaluate EncapsedStringPart $expr->value...");
        $expr->setAttribute("tainted", 0);
    }
    else if ($expr instanceof Node\Expr\FuncCall) {
        pp("evaluate funcCall {$expr->name->parts[0]}...");
        $v = eval_func($expr->name->parts[0], $expr->args, $sym_table);
        $expr->setAttribute("tainted", $v);
    }
    else if ($expr instanceof Node\Expr\MethodCall) {
        pp($expr);        
        $method_name = "{$expr->var->name}::$expr->name";
        pp("evaluate methodCall $method_name...");
        $v = eval_func($method_name, $expr->args, $sym_table);
        $expr->setAttribute("tainted", $v);
    }

    else if ($expr instanceof Node\Arg) {
        $expr->setAttribute("tainted", eval_expr($expr->value, $sym_table));
    }
            
    else {
        echo "unsupported expr type: $expr_type\n";
        var_dump($expr);
    }

    if ($expr->getAttribute("tainted") < 0) {
        if ($expr->getAttribute("tainted") == -1) {
            echo "SQL injection vulnerability found in line {$expr->getline()}\n";
        }
        else if ($expr->getAttribute("tainted") == -2) {
            echo "Command line injection vulnerability found in line {$expr->getline()}\n";
        }
    }
    return $expr->getAttribute("tainted");
}
?>