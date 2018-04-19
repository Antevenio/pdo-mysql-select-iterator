<?php

namespace PdoMysqlSelectIterator;

use PdoMysqlSelectIterator\Exception\InvalidQueryException;
use Predis\Client;

class RedisIterator implements \Iterator, Iterator
{
    protected $rowClass;
    /**
     * @var Client
     */
    protected $redis;

    /**
     * @var \PDO
     */
    protected $pdo;

    /**
     * @var string
     */
    protected $query;

    protected $count;
    protected $redisListKey;
    protected $index;
    protected $idGenerator;
    protected $isCached;
    protected $currentItem;

    public function __construct(\PDO $pdo, $query, Client $client, UniqueIdGenerator $idGenerator)
    {
        $this->assertValidQuery($query);
        $this->redis = $client;
        $this->pdo = $pdo;
        $this->query = $query;
        $this->idGenerator = $idGenerator;
        $this->redisListKey = $this->getRedisListKey();
        $this->isCached = false;
    }

    /**
     * @param $query
     * @throws InvalidQueryException
     */
    protected function assertValidQuery($query)
    {
        if (!$this->isAValidSelect($query)) {
            throw new InvalidQueryException(
                "The query provided is not a valid SELECT statement"
            );
        }
    }

    protected function isAValidSelect($query)
    {
        return (preg_match('/^\s*SELECT\s+/i',$query));
    }

    public function setRowClass($rowClass)
    {
        $this->rowClass = $rowClass;
    }

    public function rewind()
    {
        $this->cleanCache();
        $this->cacheQuery();
    }

    protected function cacheQuery()
    {
        $resultSet = $this->pdo->query($this->query);
        $this->count = 0;
        $this->index = 0;
        while ($row = $resultSet->fetch(\PDO::FETCH_ASSOC)) {
            $this->storeRowInCache($row);
            $this->count++;
        }
        $this->isCached = true;
    }

    protected function storeRowInCache($row)
    {
        $this->redis->rpush($this->redisListKey, [serialize($row)]);
    }

    protected function getRedisListKey()
    {
        return $this->idGenerator->getUniqueId();
    }

    public function count()
    {
        if (!$this->isCached) {
            $this->cacheQuery();
        }
        return $this->count;
    }

    public function key()
    {
        return $this->index;
    }

    public function valid()
    {
        return $this->index < $this->count;
    }

    public function next()
    {
        $this->index++;
    }

    public function current()
    {
        $row = unserialize($this->redis->lpop($this->redisListKey));
        if ($this->rowClass) {
            /** @var Row $rowObject */
            $rowObject = new $this->rowClass();
            $rowObject->hydrate($row);
            return $rowObject;
        }
        return $row;
    }

    protected function cleanCache()
    {
        $this->redis->del([$this->redisListKey]);
    }

    public function close()
    {
        $this->cleanCache();
    }
}