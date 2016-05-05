<?php

class SymbolTable {
    public $confidence = 1;
    public $table = [];

    public function getTable()
    {
        return $this->table;
    }
    
    public function setTable($table)
    {
        $this->table = $table;
    }

    public function getConfidence()
    {
        return $this->confidence;
    }

    public function setConfidence($confidence)
    {
        $this->confidence = $confidence;
    }
}

?>