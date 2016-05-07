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

    public function evaluate() {

    }
}

class CompoundCondition {
    public $left = NULL;
    public $op = "";
    public $right = NULL;
    
    public function __construct(Condition $cond) {
        $this->left = $cond;
    }

    public function setRight(CompoundCondition $right) {
        $this->right = $right;
    }

    public function ConcatCondition(Condition $cond, $op) {
        $new = new CompoundCondition($cond);
        $new->setOp($op);
        $new->setRight($this);
    }

    public function setOp($op) {
        $this->op = $op;
    }
    
    public function toString() {
        if (is_null($this->right)) {
            return $this->left->toString();
        }
        else {
            $left_str = $this->left->toString();
            return "($left_str $this->op {$this->right->toString()})";
        }
    }
}