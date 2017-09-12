<?php

namespace PdoMysqlSelectIterator;

class NativePDOIterator extends \PDOStatement implements Iterator
{
    protected $pdo;

    protected function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function count()
    {
        return $this->rowCount();
    }

    public function close()
    {
        parent::closeCursor();
        $this->pdo = null;
    }
}