<?php
namespace PdoMysqlSelectIterator;

class Factory {
    /**
     * @param \PDO $adapter
     * @param $query
     * @param $blockSize
     * @return Iterator
     */
    public function create(\PDO $adapter, $query, $blockSize)
    {
        if ($this->queryIsLimitable($query)) {
            return new LimitIterator($adapter, $query, $blockSize);
        } else {
            $adapter->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
            $adapter->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
            $adapter->setAttribute(
                \PDO::ATTR_STATEMENT_CLASS,
                [NativePDOIterator::class, [$adapter]]
            );
            /** @var Iterator $statement */
            $statement = $adapter->query($query);
            return $statement;
        }
    }

    protected function queryIsLimitable($query) {
        return !$this->queryHasALimitClause($query) && !$this->queryHasARandFunction($query);
    }

    protected function queryHasALimitClause($query)
    {
        return (preg_match('/\s+LIMIT\s+/i', $query));
    }

    protected function queryHasARandFunction($query)
    {
        return (preg_match('/RAND\(.*\)/i', $query));
    }
}