<?php

namespace PdoMysqlSelectIterator;

class NativePDOIterator extends \PDOStatement implements Iterator
{
    protected function __construct()
    {
    }

    public function count()
    {
        return $this->rowCount();
    }

    public function close()
    {
        parent::closeCursor();
    }
}