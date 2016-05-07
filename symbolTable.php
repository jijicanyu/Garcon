<?php
require_once 'taintInfo.php';
require_once 'utility.php';
use PhpParser\PrettyPrinter;
use PhpParser\Node;


class SymbolTable {
    /* use condition object as value */
    public $branch_condition = [];
    /* String => TaintInfo */
    public $str_table = [];
    /* String => ArrayTable */
    public $arr_table = [];
    /* String => ClassTable */
    public $cls_table = [];
    /* String => String */
    public $alias_map = [];
    public $classes = [];
    public $classes_assign_map = [];

    public function __clone()
    {
        $this->branch_condition = deep_copy_arr($this->branch_condition);
        $this->str_table = deep_copy_arr($this->str_table);
        $this->arr_table = deep_copy_arr($this->arr_table);
        $this->cls_table = deep_copy_arr($this->cls_table);
    }
    
    public function addStr($var, $info) {
        $target = $this->resolveStrAlias($var);
        if ($info->isTainted()) {
            $this->str_table[$target] = clone $info;
        }
        else {
            unset($this->str_table[$target]);
        }
    }
    
    public function getStr($var) {
        $target = $this->resolveStrAlias($var);
        if (array_key_exists($target, $this->str_table)) {
            return $this->str_table[$target];
        }
        return new TaintInfo();
    }
    
    public function getArrayTable($arr) {
        if (array_key_exists($arr, $this->arr_table) == false) {
            $this->arr_table[$arr] = new ArrayTable();
        }
        return $this->arr_table[$arr];
    }
    
    /* resolve primitive type such as string, number and boolean */
    public function resolveStrAlias($name) {
        $target = $name;
        while (array_key_exists($target, $this->alias_map)) {
            $target = $this->alias_map[$target];
        }
        pp("var $name's alias is $target...");
        return $target;
    }
    
    /* resolve object assignment */
    public function resolveClassAssign($name) {
        $target = $name;
        while (array_key_exists($target, $this->classes_assign_map)) {
            $target = $this->classes_assign_map[$target];
        }
        pp("class $name's alias is $target...");
        return $target;
    }

    public function getBranchCondition() {
        return $this->branch_condition;
    }
    
    public function getLastBranchCondition() {
        return end($this->branch_condition);
    }

    public function addBranchCondition($branch_condition) {
        array_push($this->branch_condition, $branch_condition);
    }
    
    public function mergeBranchTable($branch_table) {
        $branch_end_cond = clone $branch_table->getLastBranchCondition();

        foreach ($this->str_table as $k=>$v) {
            /* if tainted outside and untainted inside */
            if (array_key_exists($k, $branch_table->str_table) == false) {
                $branch_end_cond->setValue(false);
                $v->addTaintCondition($branch_end_cond);
            }
            /* if tainted both outside and inside */
            else {
                $v->merge($branch_table->getStr($k));
            }
        }
        
        /* if untainted outside and tainted inside */
        foreach ($branch_table->str_table as $k=>$v) {
            if (array_key_exists($k, $this->str_table) == false) {
                $v->addTaintCondition($branch_end_cond);
                $this->addStr($k, $v);
            }
        }
    }
    
    public function mergeElseTable($else_table) {
        $if_end_cond = clone $this->getLastBranchCondition();
        $else_end_cond = clone $else_table->getLastBranchCondition();
        
    }
}

class ArrayTable {
    public $scalar_table = [];
    public $uncertain_good_table = [];
    public $uncertain_bad_table = [];
    public $pending_taint;
    
    public function __clone() {
        $this->scalar_table = deep_copy_arr($this->scalar_table);
        $this->uncertain_good_table = deep_copy_arr($this->uncertain_good_table);
        $this->uncertain_bad_table = deep_copy_arr($this->uncertain_bad_table);
    }

    /* in arraytable, empty TainInfo should also be maintainted, because it is 100% untainted */
    public function addScalar($key, $info) {
        $this->scalar_table[$key] = clone $info;
//        if ($info->isTainted()) {
//            $this->scalar_table[$key] = clone $info;
//        }
//        else {
//            unset($this->scalar_table[$key]);
//        }
    }
    
    public function getScalar($name) {
        if (array_key_exists($name, $this->scalar_table)) {
            return $this->scalar_table[$name];
        }
        else {
            if (is_null($this->pending_taint)) {
                return new TaintInfo();
            }
            else {
                $new = clone $this->pending_taint;
                $new->replaceTaintCondValue($name);
                return $new;
            }
        }
    }
    
    public function addTaintedExpr($expr, $info) {
//        $code = get_stmt_str($expr);
//        $code = str_replace(";", "", $code);
        foreach ($this->scalar_table as $k=>$v) {
            $cond = new Condition();
            $cond->setExpr($expr);
            $cond->setValue($k);
            $uncertain_info = clone $info;
            $uncertain_info->addTaintCondition($cond);
            $v->merge($uncertain_info);
        }
                
        $cond = new Condition();
        $cond->setExpr($expr);
        $cond->setValue("pending");
        $uncertain_info = clone $info;
        $uncertain_info->addTaintCondition($cond);
        
        if (is_null($this->pending_taint)) {
            $this->pending_taint = $uncertain_info;
        }
        else {
            $this->pending_taint->merge($uncertain_info);
        }
    }
    
    public function addUntaintedExpr($expr, $info) {
        array_push($this->uncertain_good_table, $info);
    }
}

class ClassTable {
    public $prop_table = [];
    
    public function __clone() {
        $this->prop_table = deep_copy_arr($this->prop_table);
    }
}
?>