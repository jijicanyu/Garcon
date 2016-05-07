<?php
require_once 'utility.php';
require_once 'condition.php';
use PhpParser\PrettyPrinter;
use PhpParser\Node;

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

    public function addTaintCondition($cond) {
        foreach ($this->taint_list as $t) {
            $t->addCondition($cond);
        }
    }

    public function merge($info) {
        foreach ($info->getTaintList() as $i) {
            if ($this->isTaintTypeExist($i->type) == false) {
                $this->addSingleTaint($i);
            }
            else {
                foreach ($this->taint_list as $j) {
                    if ($i->type == $j->type) {
                        $j->merge($i);
                        break;
                    }
                    
                }
            }
        }
    }
    
    public function replaceTaintCondValue($v) {
        foreach ($this->taint_list as $t) {
            $t->replaceCondValue($v);
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
        $sani_conds = [];
        $taint_conds = [];
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
                $vul = "Other type of";
            }
            
            if ($vul != "") {
                $taint_conds = $t->getConditions();
                echo "There could be $vul vulnerability at line $lineno" . PHP_EOL;
                if (empty($branch_conds) == false) {
                    echo "When the branch conditions is satisfied:" . PHP_EOL;
                    $this->printConditions($branch_conds);
                }

                if (empty($sani_conds) == false) {
                    echo "When the sanitizing conditions is not satisfied:" . PHP_EOL;
                    $this->printConditions($sani_conds);

                }

                if (empty($taint_conds) == false) {
                    echo "When the taint conditions is satisfied:" . PHP_EOL;
                    $this->printConditions($taint_conds);
                }
                return true;
            }
        }
        
    }
    
    public function printConditions($conds) {
        $strs = [];
        foreach ($conds as $c) {
            array_push($strs, $c->toString());
        }
        echo implode(" or ", $strs) . PHP_EOL;
    }

    public function __clone() {
        $this->taint_list = deep_copy_arr($this->taint_list);
        $this->sanitize_list = deep_copy_arr($this->sanitize_list);
    }
}

class SingleTaint {
    public $type = 1;
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
    
    public function replaceCondValue($v) {
        foreach ($this->conditions as $condition) {
            $condition->setValue($v);
        }
    }
    
    public function merge($new) {
        assert($this->type == $new->type);
        if ($this->isNoCondition() || $new->isNoCondition()) {
            $this->conditions = [];
        }
        else {
            $this->conditions = array_merge($this->conditions, $new->getConditions());
        }
    }
    
    public function isNoCondition() {
        return empty($this->conditions);
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

