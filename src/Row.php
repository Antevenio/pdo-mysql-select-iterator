<?php

namespace PdoMysqlSelectIterator;

interface Row
{
    /**
     * @param array $row
     * @return mixed
     */
    public function hydrate(array $row);
}