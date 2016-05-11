<?php
require_once 'utility.php';
require_once 'condition.php';
use PhpParser\PrettyPrinter;
use PhpParser\Node;

$vul_count = 0;

class TaintInfo {
    public $taint_list = [];
    public $sanitize_list = [];

    public function getTaintList()
    {
        return $this->taint_list;
    }
    
    public function getSanitizedList()
    {
        return $this->sanitize_list;
    }
    
    public function isTainted() {
        return !empty($this->taint_list);
    }

    public function addTaintCondition($cond, $op) {
        foreach ($this->taint_list as $t) {
            $t->addCondition($cond, $op);
        }
    }
    
    public function setTaintCondition($cond) {
        foreach ($this->taint_list as $t) {
            $t->setCondition($cond);
        }
    }
    
    public function negateTaintCondition() {
        foreach ($this->taint_list as $item) {
            $item->negateCondition();
        }
    }

    public function merge($info, $op) {
        foreach ($info->getTaintList() as $i) {
            if ($this->isTaintTypeExist($i->type) == false) {
                $this->addSingleTaint($i);
            }
            else {
                foreach ($this->taint_list as $j) {
                    if ($i->type == $j->type) {
                        $j->merge($i, $op);
                        break;
                    }
                    
                }
            }
        }
    }
    
    public function replaceTaintCondValue($old, $new) {
        foreach ($this->taint_list as $t) {
            $t->replaceCondValue($old, $new);
        }
    }

    public function addSingleTaint($t) {
        array_push($this->taint_list, clone $t);
    }
    
    public function addSanitize($s) {
        if ($this->isSanitizeTypeExist($s->type) == false) {
            array_push($this->sanitize_list, clone $s);
        }
    }
    
    public function isSanitizeTypeExist($sani_type) {
        foreach ($this->taint_list as $item) {
            if ($item->type == $sani_type) {
                return true;
            }
        }
        return false;
    }
    
    public function isTaintTypeExist($tain_type) {
        foreach ($this->taint_list as $item) {
            if ($item->type == $tain_type) {
                return true;
            }
        }
        return false;
    }

    public function checkVul($sink, $lineno, $sym_table) {
        global $vul_count;
        $sani_conds = NULL;
        $taint_conds = NULL;
        $branch_conds = $sym_table->getBranchCondition();
        $vul = "";
        
        foreach ($this->sanitize_list as $s) {
            if ($s->type == $sink) {
                if ($s->isNoCondition()) {
                    /* vulnerability has 100% been sanitized */
                    echo "vulnerability is sanitized at line $lineno";
                    /* jump out, no need to check more */
                    return false;
                }
                /* if there is a condition */
                else {
                    $sani_conds = $s->getConditions();
                    break;
                }
            }
        }
        
        /* print vul for curretn sink */
        foreach ($this->taint_list as $t) {
            $source = $t->type;
            if ($source == 1 && $sink == 1) {
                $vul = "SQL injection";
            } else if ($source == 1 && $sink == 2) {
                $vul = "Command line injection";
            } else if ($source == 2 && $sink == 4) {
                $vul = "Persisted XSS";
            } else {
                continue;
            }

            $branch_conds = $branch_conds->simplify();
            if ($vul != "") {
                $vul_count++;
                $taint_conds = $t->getCondition()->simplify();
                if (!is_null($taint_conds) && $taint_conds->isAlwaysFalse()) {
                    continue;
                }
                echo "There is a $vul vulnerability at line $lineno" . PHP_EOL;
                if (is_null($branch_conds) == false) {
                    echo "When the branch(sink) conditions is satisfied:" . PHP_EOL;
                    echo $branch_conds->toString() . PHP_EOL;
                }

                if (is_null($sani_conds) == false) {
                    echo "When the sanitizing conditions is not satisfied:" . PHP_EOL;
                    echo $sani_conds->toString() . PHP_EOL;
                }

                if (is_null($taint_conds) == false) {
                    echo "When the taint(source) conditions is satisfied:" . PHP_EOL;
                    echo $taint_conds->toString() . PHP_EOL;
                }
                return true;
            }
        }
        
    }

    public function __clone() {
        $this->taint_list = deep_copy_arr($this->taint_list);
        $this->sanitize_list = deep_copy_arr($this->sanitize_list);
    }
}

class SingleTaint {
    public $type = 1;
    public $condition = NULL;

    public function getType()
    {
        return $this->type;
    }

    public function setType($type)
    {
        $this->type = $type;
    }

    public function addCondition($c, $op) {
        if (is_null($this->condition)) {
            if ($op == "or") {
                /* some condition || NULL condition = NULL
                /* remain NULL */
            }
            else {
                $this->condition = $c;
            }
        }
        
        else {
            if ($this->condition instanceof Condition) {
                $this->condition = $this->condition->concatCondition($c, $op);
            }
            else if ($this->condition instanceof CompoundCondition) {
                $this->condition = $this->condition->concatCondition($c, $op);
            }
            else {
                /* shouldn't be here */
                assert(false, "condition should either be a Condition object or CompounCondition object");
            }
        }
    }
    
    public function setCondition($cond) {
        $this->condition = $cond;
    }

    public function getCondition()
    {
        return $this->condition;
    }
    
    public function negateCondition() {
        $this->condition = $this->condition->setNot();
    }
    
    public function replaceCondValue($old, $new) {
        $this->condition->replaceValue($old, $new);
    }
    
    public function merge($info, $op) {
        assert($this->type == $info->type);
        if ($this->isNoCondition() || $info->isNoCondition()) {
            $this->condition = NULL;
        }
        else {
            $c1 = $this->getCondition();
            $c2 = $info->getCondition();
            $new = $c1->concatCondition($c2, $op);
            $this->setCondition($new);
        }
    }
    
    public function isNoCondition() {
        return empty($this->condition);
    }
}

class SingleSanitize {
    public $type = 0;
    public $conditions = [];

    public function getType()
    {
        return $this->type;
    }

    public function setType($type)
    {
        $this->type = $type;
    }

    public function addCondition($c) {
        array_push($this->conditions, $c);
    }

    public function getConditions()
    {
        return $this->conditions;
    }

    public function isNoCondition() {
        return empty($this->conditions);
    }
}

