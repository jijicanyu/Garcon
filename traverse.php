#!/usr/bin/env php
<?php
require './vendor/autoload.php';
use PhpParser\Error;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

// class TaintVar {
//     public $value = 0;
//     public $type = "";
//     function __construct($taint_value, $type) {
//         $this->value = $taint_value;
//         $this->type = $type;
//     }
// }

class TaintInfo {
    public $value = 0;
    public $certainty = 1;
    function __construct($v, $c) {
        $this->value = $v;
        $this->certainty = $c;
    }
}


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
$user_funcs = []; // a map of user defined functions

$cond_mode = 0;

/* construct funcs dict */
foreach($stmts as $stmt) {
    if ($stmt instanceof Node\Stmt\Function_) {
        $user_funcs[$stmt->name] = $stmt;
    }
}
do_statements($stmts, $tainted_vars);
pp($code);
pp($tainted_vars);

function get_left_side_name($expr) {
    if ($expr instanceof Node\Expr\Variable) {
        return $expr->name;
    }
    else if ($expr instanceof Node\Expr\ArrayDimFetch) {
        //return $expr->var->name . $expr->dim->value;
        return $expr->var->name;
    }
    else if ($expr instanceof Node\Arg) {
        //return $expr->var->name . $expr->dim->value;
        return $expr->value->name;
    }

    else if ($expr instanceof Node\Param) {
        return $expr->name;
    }

    else {
        echo "unsupported left side value\n";
        pp($expr);
    }
}

function union_tables($t1, $t2) {
    foreach($t1 as $e1=>$obj1) {
        if (array_key_exists($e1, $t2)) {
            $obj2 = $t2[$e2];
            $obj2->certainty = max($obj1->certainty, $obj2->certainty);
        }
        else {
            $t2[$e1] = $obj1;
        }
    }
    return $t2;
}

function augment_table($out, $in) {
    global $cond_mode;
    $confidence = 1;
    if ($cond_mode > 0) {
        $confidence = 0.5;
    }
    foreach ($in as $k=>$v) {
        if (!array_key_exists($k, $out)) {
            $v->certainty *= $confidence;
            $out[$k] = $v;
            pp("add $k, certainty: $v->certainty");
        }
    }
    foreach ($out as $k=>$v) {
        if (!array_key_exists($k, $in)) {
            $v->certainty -= $v->certainty*$confidence;
            pp("update $k, certainty: $v->certainty");
        }
        if ($v->certainty == 0) {
            unset($out[$k]);
            pp("unset $k");
        }
    }
    return $out;
}

function do_statements($func_stmts, &$sym_table) {
    global $cond_mode;
    /* only consider assign for now */
    foreach ($func_stmts as $stmt) {
        if ($stmt instanceof Node\Expr\Assign) {
            pp("process assign...");
            //pp($stmt->var);
            $taint_info = eval_expr($stmt->expr, $sym_table);
            $left = get_left_side_name($stmt->var);
            if ($taint_info->value > 0) {
                /* should also add class */
                if ($stmt->expr instanceof Node\Expr\ArrayDimFetch) {
                    $sym_table[$left] = new TaintInfo($taint_info->value, $taint_info->certainty/2);
                }
                
                else {
                    $sym_table[$left] = $taint_info;
                }

                pp("add {$left}");
            }
            else if (check_var($left, $sym_table)->value != 0) {
                /* delete entry in sym_table */
                //if ($cond_mode == 0) {
                    unset($sym_table[$left]);
                    pp("unset {$left}");
                //}
              
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
        /* ignore ifelse for now */
        else if ($stmt instanceof Node\Stmt\If_) {
            $out_table = $sym_table;
            
            $cond_mode += 1;
            do_statements($stmt->stmts, $sym_table);
            $table1 = $sym_table;
            if (is_null($stmt->else) != true) {
                do_statements($stmt->else->stmts, $sym_table);
                $table2 = $sym_table;
                $sym_table = union_tables($table1, $table2);
            }
            $cond_mode -= 1;

            $sym_table = augment_table($out_table, $sym_table);
        }
        
        else if ($stmt instanceof Node\Stmt\While_) {
            $cond_mode += 1;
            do_statements($stmt->stmts, $sym_table);
            $cond_mode -= 1;
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
    //pp($sym_table);
    return new TaintInfo(0, 1);
}

function gen_callee_table($args, $func_proto, $caller_table) {
    $newtable = [];
    $params = $func_proto->params;
    for ($i = 0; $i < count($params); $i++) {

        $left = $params[$i];
        $right = $args[$i];
        $taint_info = eval_expr($right, $caller_table);
        $var_name = get_left_side_name($left);
        if ($taint_info->value > 0) {
            pp("add {$var_name}");
            if ($right instanceof Node\Expr\ArrayDimFetch) {
                $newtable[$var_name] = new TaintInfo($taint_info->value, $taint_info->certainty/2);
            }
            else {
                $newtable[$var_name] = $taint_info;
            }
        }
        else {
            
        }
    }
    return $newtable;
}

function check_var($name, $sym_table) {
    pp("check var $name");
    if (array_key_exists($name, $sym_table)) {
        return $sym_table[$name];
    }
    else {
        return new TaintInfo(0, 1);
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
        $taint_value = eval_expr($arg, $sym_table);
        if ($taint_value->value != 0) {        
            return $taint_value;
        }
    }
    return new TaintInfo(0, 1);
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

function eval_func($func_name, $args, &$sym_table) {
    global $user_funcs;
    $source_type = is_source($func_name);
    $sink_type = is_sink($func_name);
    if ($sink_type != 0) {
        $taint_info = is_args_tainted($args, $sym_table);
        if ($taint_info->value)
        {
            return new TaintInfo(-$sink_type, $taint_info->certainty);
        }
        return new TaintInfo(0, 1);
        
    }
    else if ($source_type != 0) {
        return new TaintInfo($source_type, 1);
    }
    /* if user defined function */
    else if (array_key_exists($func_name, $user_funcs)) {
        $func_proto = $user_funcs[$func_name];
        $callee_table = gen_callee_table($args, $func_proto, $sym_table);
        return do_statements($func_proto->stmts, $callee_table);        
    }
    /* if built-in function */
    else {
        /* special cases */
        if ($func_name == "array_push") {
            $v = eval_expr($args[1], $sym_table);
            $sym_table[get_left_side_name($args[0])] = $v;
            $args[0]->setAttribute("tainted", $v);
            return $v;
        }
        else {
            return is_args_tainted($args, $sym_table);
        }

        
    }
}

function eval_expr($expr, &$sym_table) {
    $expr_type = get_class($expr);

    if ($expr instanceof Node\Expr\Variable) {
        pp("evaluate var {$expr->name}...");
        $expr->setAttribute("tainted", check_var($expr->name, $sym_table));
    }
    else if ($expr instanceof Node\Scalar\LNumber) {
        pp("evaluate lnumber {$expr->value}...");
        $expr->setAttribute("tainted", new TaintInfo(0, 1));
    }
    else if ($expr instanceof Node\Scalar\String_) {
        pp("evaluate string {$expr->value}...");
        $expr->setAttribute("tainted", new TaintInfo(0, 1));
    }

    else if ($expr instanceof Node\Expr\ArrayDimFetch) {
        pp("evaluate arraydimfetch...");
        //pp($expr);
        /* set certainty to 2 since array fetch will lose half certainty */
        if (is_source($expr->var->name)) {
            $expr->setAttribute("tainted", new TaintInfo(1, 2));
        }
        else {
            $expr->setAttribute("tainted", eval_expr($expr->var, $sym_table));
            //pp($expr);
        }
    }

    else if ($expr instanceof Node\Expr\BinaryOp) {
        pp("evaluate binaryOp $expr_type...");
        $left_v = eval_expr($expr->left, $sym_table);
        $right_v = eval_expr($expr->right, $sym_table);
        if ($left_v->value) {
            $expr->setAttribute("tainted", $left_v);
        }
        else if ($right_v->value) {
            $expr->setAttribute("tainted", $right_v);
        }
        else {
            $expr->setAttribute("tainted", new TaintInfo(0, 1));
        }
    }

    else if ($expr instanceof Node\Scalar\Encapsed) {
        pp("evaluate binaryOp $expr_type...");
        $is_set = false;
        foreach($expr->parts as $part) {
            if (eval_expr($part, $sym_table)) {
                $expr->setAttribute("tainted", new TaintInfo(1, 1));
                $is_set = true;
                break;
            }
        }
        if (!$is_set) {
            $expr->setAttribute("tainted", new TaintInfo(0, 1));
        }
        
    }

    else if ($expr instanceof Node\Scalar\EncapsedStringPart) {
        pp("evaluate EncapsedStringPart $expr->value...");
        $expr->setAttribute("tainted", new TaintInfo(0, 1));
    }

    else if ($expr instanceof Node\Expr\FuncCall) {
        pp("evaluate funcCall {$expr->name->parts[0]}...");
        $func_name = $expr->name->parts[0];

        $v = eval_func($func_name, $expr->args, $sym_table);
        $expr->setAttribute("tainted", $v);
    }

    else if ($expr instanceof Node\Expr\MethodCall) {
        $method_name = "{$expr->var->name}::$expr->name";
        pp("evaluate methodCall $method_name...");
        $v = eval_func($method_name, $expr->args, $sym_table);
        $expr->setAttribute("tainted", $v);
    }

    else if ($expr instanceof Node\Arg) {
        pp("evaluate arg $expr_type");
        $v = eval_expr($expr->value, $sym_table);
        $expr->setAttribute("tainted", $v);
    }
            
    else if ($expr instanceof Node\Expr\Array_) {
        $expr->setAttribute("tainted", new TaintInfo(0, 1));
    }
            
    else {
        echo "unsupported expr type: $expr_type\n";
        //var_dump($expr);
    }

    
    $return_info = $expr->getAttribute("tainted");
    pp("return ".$return_info->value);
    //pp($return_info);
    if ($return_info->value < 0) {
        if ($return_info->value == -1) {
            echo "SQL injection vulnerability found in line {$expr->getline()}\n";
        }
        else if ($return_info->value == -2) {
            echo "Command line injection vulnerability found in line {$expr->getline()}\n";
        }
    }  
    return $expr->getAttribute("tainted");
}
?>