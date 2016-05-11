#!/usr/bin/env php
<?php
require_once './vendor/autoload.php';
require_once 'symbolTable.php';
require_once 'taintInfo.php';
require_once 'utility.php';
require_once 'preprocessor.php';
use PhpParser\Error;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

/* initialize parser and traverser */
$parser        = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
$traverser     = new NodeTraverser;


/* read and parse code from stdin */

/* for debug */
//$code = file_get_contents("demo/function.php");
$code = file_get_contents("php://stdin");
$stmts = $parser->parse($code);

/* initialize maps to maintain information */
$tainted_vars = new SymbolTable();
$user_funcs = []; // a map of top-level user defined functions

/* initialize taint sources */
$sources['input'] = ['_GET'=>1, '_POST'=>1, '_COOKIE'=>1, '_ENV'=>1];
$sources['database'] = ['file_get_contents'=>1, 'mysql_fetch_row'=>1];

/* initialize sinks */
$sinks['sql'] = ['pg_query'=>1, 'mysql_query'=>1, 'mysqli::query'=>1];
$sinks['cmd'] = ['system'=>1];
$sinks['xss'] = ['print_'=>1];

/* initialize sanitizing routine */
$sani_funcs['sql'] = ['escape_sql_string'=>1];
$sani_funcs['cmd'] = ['escapeshellcmd'=>1];
$sani_funcs['xss'] = ['htmlspecialchars'=>1];                 
                   
/* deprecated */
$cond_mode = 0;


/* construct funcs dict */
foreach($stmts as $stmt) {
    if ($stmt instanceof Node\Stmt\Function_) {
        $user_funcs[$stmt->name] = $stmt;
    }
}
do_statements($stmts, $tainted_vars);
// pp($code);
//pp($tainted_vars);

fclose($log);

function do_assign($left, $right, &$sym_table) {
    /* special case for simple class assignment */
    if ($right instanceof Node\Expr\Variable) {
        if ($left instanceof Node\Expr\Variable) {
            if ($sym_table->isClass($right->name)) {
                $sym_table->registerClass($left->name);
                $sym_table->addClassAssign($left->name, $right->name);
            }
        }
        else {
            /* ignore for now */
        }
    }

    if ($right instanceof Node\Expr\New_) {
        $cls_name = $left->name;
        if ($sym_table->isClass($cls_name)) {
            $sym_table->invalidateClassMap($cls_name);
        }
        else {
            $sym_table->registerClass($cls_name);
        }
    }

    // @new
    $info = eval_expr($right, $sym_table);
    if ($left instanceof Node\Expr\Variable) {
        $sym_table->mergeStr($left->name, $info);
        $left->setAttribute("tainted", clone $info);
    }

    else if ($left instanceof Node\Expr\ArrayDimFetch) {
        $arr_table = $sym_table->getArrayTable($left->var->name);
        $b_cond = $sym_table->getBranchCondition();
        if ($left->dim instanceof Node\Scalar) {
            $arr_table->addScalar($left->dim->value, $info, $b_cond);
        }
        else {
            if ($info->isTainted()) {
                $arr_table->addTaintedExpr($left->dim, $info, $b_cond);
            }
            else {
                $arr_table->addUntaintedExpr($left->dim, $info, $b_cond);
            }
        }
    }

    else if ($left instanceof Node\Expr\PropertyFetch) {
        $target_class = $sym_table->resolveClassAssign($left->var->name);
        $class_table = $sym_table->getClassTable($target_class);
        $b_cond = $sym_table->getBranchCondition();
        $prop = $left->name;
        $class_table->addProp($prop, $info, $b_cond);
        $sym_table->registerClass($target_class);
    }
//    $left_name = get_left_side_name($left);
//    if ($taint_info->value > 0) {
//        $left->setAttribute("tainted", clone $taint_info);
//        set_var($left,$sym_table);
//        pp("set $left_name");
//    }
//    else {
//        $target = get_alias($left_name);
//        if (get_var($target, $sym_table)->value != 0) {
//            unset($sym_table[$target]);
//            pp("unset $left_name");
//        }
//    }
    return $info;
}

function get_array_taint_info($expr, $sym_table) {
    assert($expr instanceof Node\Expr\ArrayDimFetch);
    $arr_table = $sym_table->getArrayTable($expr->var->name);
    if ($expr->dim instanceof Node\Scalar) {
        return $arr_table->getScalar($expr->dim->value);
    }
    else {
        // todo
        echo "You hit an unimplemented feature!!";
        exit(1);
    }
}

function do_statements($func_stmts, &$sym_table) {
    global $vul_count;
    /* only consider assign for now */
    foreach ($func_stmts as $stmt) {
        /* assignment is the most common expression */
        if ($stmt instanceof Node\Expr) {
            pp("process expr...");
            eval_expr($stmt, $sym_table);
        }
        else if ($stmt instanceof Node\Stmt\Function_) {
            pp("process function declaration");
            /* skip declare statement */
            // TODO: implement nested funtions
            continue;
        }
        else if ($stmt instanceof Node\Stmt\If_) {
            $cond = new Condition();
            $cond->setExpr($stmt->cond);
            $cond->setValue(true);
            $sym_table->pushBranchCondition(clone $cond);
            do_statements($stmt->stmts, $sym_table);
            $sym_table->popBranchCondition();

            if (is_null($stmt->else) == false) {
                $cond->setValue(false);
                $sym_table->pushBranchCondition(clone $cond);
                do_statements($stmt->else->stmts, $sym_table);
                $sym_table->popBranchCondition();
            }

        }

        else if ($stmt instanceof Node\Stmt\While_) {
            $before = $vul_count;
            $cond = new Condition();
            $cond->setExpr($stmt->cond);
            $cond->setValue(true);
            $cond->setRoundStr(0);
            $sym_table->pushBranchCondition(clone $cond);
            do_statements($stmt->stmts, $sym_table);
            $sym_table->popBranchCondition();

            if ($vul_count == $before) {
                $cond = new Condition();
                $cond->setExpr($stmt->cond);
                $cond->setValue(true);
                $cond->setRoundStr(1);
                $sym_table->pushBranchCondition(clone $cond);
                do_statements($stmt->stmts, $sym_table);
                $sym_table->popBranchCondition();
            }

        }
        /* ignore ifelse for now */

        else {
            //pp("process unsupported statement");
            echo "unsupported statement type ".get_class($stmt)."\n";
        }
    }
    return new TaintInfo();
}

function is_sink($func_name) {
    global $sinks;
    if (array_key_exists($func_name, $sinks['sql'])) {
        return 1;
    }
    else if (array_key_exists($func_name, $sinks['cmd'])) {
        return 2;
    }
    else if (array_key_exists($func_name, $sinks['xss'])) {
        return 4;
    }
    else {
        return false;
    }
}

function is_source($name) {
    global $sources;
    if (array_key_exists($name, $sources['input'])) {
        return 1;
    }
    else if (array_key_exists($name, $sources['database'])) {
        return 2;
    }
    else {
        return 0;
    }
}

function is_sanitize($name) {
    global $sani_funcs;
    if (array_key_exists($name, $sani_funcs['sql'])) {
        return 1;
    }
    else if (array_key_exists($name, $sani_funcs['cmd'])) {
        return 2;
    }
    else if (array_key_exists($name, $sani_funcs['xss'])) {
        return 4;
    }
    else {
        return 0;
    }
}

function is_args_tainted($args, $sym_table) {
    foreach($args as $arg) {
        $info = eval_expr($arg, $sym_table);
        if ($info->isTainted()) {
            return $info;
        }
    }
    return new TaintInfo();
}

function gen_callee_table($args, $func_proto, $caller_table) {
    $newtable = clone $caller_table;
    $params = $func_proto->params;
    for ($i = 0; $i < count($params); $i++) {
        $left = $params[$i]->name;
        $right = $args[$i]->value->name;
        if ($caller_table->isInStrTable($right)) {
            $newtable->strTableReplaceKey($right, $left);
        }
        else if ($caller_table->isInArrTable($right)) {
            $newtable->arrTableReplaceKey($right, $left);
        }
        else if ($caller_table->isInClsTable($right)) {
            //$newtable->setSpecialClassTable();
            $newtable->clsTableReplaceKey($right, $left);

        }
        else {
            
        }
    }
    return $newtable;
}

function eval_func($func_name, $args, &$sym_table) {
    global $user_funcs;
    $source_type = is_source($func_name);
    $sink_type = is_sink($func_name);
    $info = is_args_tainted($args, $sym_table);

    /* if sink */
    if ($sink_type != 0) {
        $info->checkVul($sink_type, $args[0]->getline(), $sym_table);
        return new TaintInfo();
    }
    
    if ($source_type != 0) {
        $newInfo = new TaintInfo();
        $t = new SingleTaint();
        $t->setType($source_type);
        $newInfo->addSingleTaint($t);
        return $newInfo;
    }
    /* if user defined function */
    // todo
    else if (array_key_exists($func_name, $user_funcs)) {
        $func_proto = $user_funcs[$func_name];
        $callee_table = gen_callee_table($args, $func_proto, $sym_table);
        $return = do_statements($func_proto->stmts, $callee_table);
        return $return;
    }
    /* if built-in function */
    else {

        /* special cases */
        // todo
        if ($func_name == "array_push") {
            $v = eval_expr($args[1], $sym_table);
            $sym_table[get_left_side_name($args[0])] = $v;
            $args[0]->setAttribute("tainted", $v);
            return $v;
        }
        /* return the argument's taint info */
        else {
            return $info;
        }
    }
}

function eval_expr($expr, &$sym_table) {

    $expr_type = get_class($expr);
    $info = new TaintInfo();

    // @new
    if ($expr instanceof Node\Expr\Variable) {
        pp("evaluate var {$expr->name}...");
        $info = $sym_table->getStr($expr->name);
    }

    else if ($expr instanceof Node\Scalar\LNumber) {
        pp("evaluate lnumber {$expr->value}...");
    }
    else if ($expr instanceof Node\Scalar\String_) {
        pp("evaluate string {$expr->value}...");
    }

    else if ($expr instanceof Node\Expr\ArrayDimFetch) {
        pp("evaluate arraydimfetch...");
        /* if the array is taint source */
        if (is_source($expr->var->name)) {
            $taint = new SingleTaint();
            $taint->setType(1);
            $info->addSingleTaint($taint);
            // echo $nodePrinter->prettyPrint([$expr]);
        }
        /* if not */
        else {
            $info = get_array_taint_info($expr, $sym_table);
        }

    }

    else if ($expr instanceof Node\Expr\PropertyFetch) {
        pp("evaluate propertyfetch...");
        $class_name = $expr->var->name;
        $resolved_name = $sym_table->resolveClassAssign($class_name);
        pp("resolved class: $resolved_name");
        
        $prop_name = $expr->name;
        $class_table = $sym_table->getClassTable($resolved_name);
        $info = $class_table->getProp($prop_name);
        //$classes[$expr->var->name] = 1;
    }

    else if ($expr instanceof Node\Expr\BinaryOp) {
        pp("evaluate binaryOp $expr_type...");
        $left_v = eval_expr($expr->left, $sym_table);
        $right_v = eval_expr($expr->right, $sym_table);

        if ($left_v->isTainted()) {
            $info = $left_v;
        }
        else if ($right_v->isTainted()) {
            $info = $right_v;
        }
        else {
            /* pass */
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
        $info = eval_func($func_name, $expr->args, $sym_table);
    }

    else if ($expr instanceof Node\Expr\MethodCall) {
        $method_name = "{$expr->var->name}::$expr->name";
        pp("evaluate methodCall $method_name...");
        $info = eval_func($method_name, $expr->args, $sym_table);
        
    }

    else if ($expr instanceof Node\Arg) {
        pp("evaluate arg $expr_type");
        $info = eval_expr($expr->value, $sym_table);
        
    }
            
    else if ($expr instanceof Node\Expr\Array_) {
        $expr->setAttribute("tainted", new TaintInfo(0, 1));
    }

    else if ($expr instanceof Node\Expr\Assign) {
        return do_assign($expr->var, $expr->expr, $sym_table);
    }

    else if ($expr instanceof Node\Expr\AssignRef) {
        return do_assignref($expr->var, $expr->expr, $sym_table);
    }

    else if ($expr instanceof PhpParser\Node\Expr\New_) {

    }
    
    else {
        echo "unsupported expr type: $expr_type\n";
        pp($expr);
    }

    

    $expr->setAttribute("tainted", clone $info);
    return clone $info;
}


function do_assignref($left, $right, $sym_table) {
    global $alias_map;
    $alias_map[get_left_side_name($left)] = $right->name;
    return get_var($right->name, $sym_table);
}

?>