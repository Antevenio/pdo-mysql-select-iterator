<?php
namespace PdoMysqlSelectIterator\Test;

use PdoMysqlSelectIterator\Row;

class TestRow implements Row
{
    protected $row;

    public function hydrate(array $row)
    {
        $this->row = $row;
    }

    public function getRow()
    {
        return $this->row;
    }
}