<?php

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

    public function addTaint($t, $c) {
        if ($this->isTaintTypeExist($t) == false) {
            array_push($this->taint_list, clone $t);
        }
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

    public function __clone() {
        $new = [];
        foreach ($this->taint_list as $i) {
            array_push($new, clone $i);
        }
        $this->list = $new;
    }
}

class SingleTaint {
    public $type = 1;
    public $certainty = 1;
    public $sanitized = false;

    public function getType()
    {
        return $this->type;
    }

    public function getCertainty()
    {
        return $this->certainty;
    }

    public function isSanitized()
    {
        return $this->sanitized;
    }

    public function setCertainty($certainty)
    {
        $this->certainty = $certainty;
    }

    public function setType($type)
    {
        $this->type = $type;
    }

    public function setSanitized($sanitized)
    {
        $this->sanitized = $sanitized;
    }
}

class Condition {
    public $expr;
    public $value;

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
}