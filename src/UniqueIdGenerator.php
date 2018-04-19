<?php

namespace PdoMysqlSelectIterator;

class UniqueIdGenerator
{
    public function getUniqueId()
    {
        return uniqid();
    }
}