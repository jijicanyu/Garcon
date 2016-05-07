<?php
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter;
use PhpParser\Node;

require_once 'symbolTable.php';

class Preprocessor {
    public $stmts;
    public $sym_table;

    public function __construct($tree) {
        $this->stmts = $tree;
        $this->sym_table = new SymbolTable();
    }

    public function renameClass() {
        return $this->stmts;
    }
    
    public function do_statements() {
        foreach ($this->stmts as $stmt) {
            if ($stmt instanceof Node\Expr) {
                $this->do_expression($stmt);
            }
            else {
                // todo
                echo "You hit an unimplemented feature!!";
                exit(1);
            }
        }
    }
    
    public function do_expression($expr) {
        if ($expr instanceof Node\Expr\Assign) {
            $this->do_assignment($expr);
        }
        
        else if ($expr instanceof Node\Expr\PropertyFetch) {
            $this->sym_table->registerClass($expr->var->name);
        }
        
        else if ($expr instanceof PhpParser\Node\Expr\New_) {
            $this->sym_table->registerClass($expr->class->parts[0]);
        }
        
        else {
            // todo
            echo "You hit an unimplemented feature!!";
            exit(1);
        }
    }
    
    public function do_assignment($expr) {
        $this->do_expression($expr->left);
        $this->do_expression($expr->right);
        
    }
}