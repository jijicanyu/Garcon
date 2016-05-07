<?php
use PhpParser\PrettyPrinter;
use PhpParser\Node;

class Condition {
    public $expr;
    public $value;
    public $relation = "==";

    public function toString() {
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
        if ($this->relation == "==") {
            $this->relation = "!=";
        }
        else {
            $this->relation = "==";
        }
    }

    public function evaluate() {

    }
    
    
}

class CompoundCondition {
    /* left is a compound, right is a single condition */
    public $left = NULL;
    public $op = "";
    public $right = NULL;
    
    public function __construct(Condition $cond) {
        $this->right = $cond;
    }

    public function setLeft(CompoundCondition $left) {
        $this->left = $left;
    }

    public function ConcatCondition(Condition $cond, $op) {
        $new = new CompoundCondition($cond);
        $new->setOp($op);
        $new->setLeft($this);
    }
    
    public function ConcatCompoundCondition(CompoundCondition $cond, $op) {
        
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
        if (is_null($this->op)) {
            $this->right->setNot();
        }
        else {
            $this->right->setNot();
            $this->notOp();
            $this->left->setNot();
        }
    }
    
    public function toString() {
        if (is_null($this->left)) {
            return $this->right->toString();
        }
        else {
            $right_str = $this->right->toString();
            return "({$this->left->toString()} $this->op $right_str)";
        }
    }
}