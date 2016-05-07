<?php
use PhpParser\PrettyPrinter;
use PhpParser\Node;

class Condition {
    public $expr;
    public $value;
    public $relation = "==";
    public $true = NULL;
    public $false = NULL;

    public function toString() {
        if ($this->true) {
            return "true";
        }

        if ($this->false) {
            return "false";
        }
        
        $expr_str = get_stmt_str($this->expr);
        $expr_str = str_replace(";", "", $expr_str);
        if (is_string($this->value)) {
            return "$expr_str $this->relation \"$this->value\"";
        }
        else if (is_int($this->value)) {
            return "$expr_str $this->relation $this->value";
        }
        else if (is_bool($this->value)) {
            $bool_str = ($this->value) ? 'true' : 'false';
            return "$expr_str $this->relation $bool_str";
        }
        else {
            assert($this->value instanceof Node\Expr);
            $value_expr = get_stmt_str($this->value);
            $value_expr = str_replace(";", "", $value_expr);
            return "$expr_str $this->relation $value_expr";
        }
    }

    public function getExpr()
    {
        return $this->expr;
    }

    public function setExpr($expr)
    {
        $this->expr = $expr;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function setValue($value)
    {
        $this->value = $value;
    }

    public function setRelation($relation) {
        $this->relation = $relation;
    }
    
    public function setNot() {
        if ($this->true) {
            $this->setAlwaysFalse();
        }
        else if ($this->false) {
            $this->setAlwaysTrue();
        }
        else {
            if ($this->relation == "==") {
                $this->relation = "!=";
            }
            else {
                $this->relation = "==";
            }
        }
        
    }

    public function evaluate() {

    }
    
    public function concatCondition($c, $op) {
        $new = new CompoundCondition($this, $c, $op);
        return $new;
    }
    
    public function setAlwaysTrue() {
        $this->true = true;
        $this->false = false;
    }
    
    public function isAlwaysTrue() {
        if ($this->true != NULL) {
            return $this->true;
        }
        else {
            return false;
        }
    }
    
    public function isAlwaysFalse() {
        if ($this->false != NULL) {
            return $this->false;
        }
        else {
            return false;
        }
    }
    
    public function setAlwaysFalse() {
        $this->true = false;
        $this->false = true;
    }
    
    public function simplify() {
        if ($this->true) {
            return NULL;
        }
    }
    
    public function replaceValue($old, $new) {
        if ($this->value == $old) {
            $this->value = $new;
        }
    }
}

class CompoundCondition {
    /* left is a compound, right is a single condition */
    public $left = NULL;
    public $op = "";
    public $right = NULL;
    
    public function __construct($l, $r, $op) {
        $this->left = $l;
        $this->right = $r;
        $this->op = $op;
    }

    public function setLeft(CompoundCondition $left) {
        $this->left = $left;
    }

    public function concatCondition($cond, $op) {
        $new = new CompoundCondition($this, $cond, $op);
        return $new;
        
    }
    
    public function concatCompoundCondition(CompoundCondition $cond, $op) {
        
    }

    public function setOp($op) {
        $this->op = $op;
    }
    
    public function notOp() {
        assert($this->op != "");
        if ($this->op == "and") {
            $this->op = "or";
        }
        else {
            $this->op = "and";
        }
    }
    
    public function setNot() {
        $this->notOp();
        $this->left->setNot();
        $this->right->setNot();
    }
    
    public function replaceValue($old, $new) {
        $this->left->replaceValue($old, $new);
        $this->right->replaceValue($old, $new);
    }
    
    public function toString() {
        return "({$this->left->toString()} $this->op {$this->right->toString()})";
    }
}