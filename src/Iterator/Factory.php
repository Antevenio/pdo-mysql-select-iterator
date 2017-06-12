<?php
namespace PdoMysqlSelectIterator\Iterator;

use PdoMysqlSelectIterator\Iterator;

class Factory {
    public function create(\PDO $adapter, $query, $blockSize)
    {
        return new Iterator($adapter, $query, $blockSize);
    }
}