<?php

namespace PdoMysqlSelectIterator;

interface Iterator extends \Countable, \Traversable
{
    public function close();
}