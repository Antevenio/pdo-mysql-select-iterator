<?php
namespace PdoMysqlSelectIterator;

use PdoMysqlSelectIterator\Exception\InvalidQueryException;

class Iterator implements \Iterator, \Countable
{
    const BLOCK_SIZE = 1000;

    const _NOT_COUNTING = 0;
    const _COUNTING = 1;

    /**
     * @var \PDO
     */
    protected $pdo;

    /**
     * @var string
     */
    protected $query;

    /**
     * @var int
     */
    protected $blockSize;

    /**
     * @var int
     */
    protected $currentBlockIndex;

    /**
     * @var int
     */
    protected $absoluteIndex;

    /**
     * @var int
     */
    protected $totalRows;

    /**
     * @var array
     */
    protected $results;

    public function __construct(\PDO $pdo, $query, $blockSize = self::BLOCK_SIZE)
    {
        $this->assertValidQuery($query);
        $this->pdo = $pdo;
        $this->query = $query;
        $this->blockSize = $blockSize;
        $this->currentBlockIndex = 0;
        $this->absoluteIndex = 0;
        $this->results = null;
        $this->totalRows = null;
    }

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

    public function next()
    {
        $this->absoluteIndex++;
        $this->currentBlockIndex++;
        if ($this->endOfBlockReached()) {
            $this->fetchBlock();
            $this->currentBlockIndex = 0;
        }
    }

    protected function endOfBlockReached()
    {
        return ($this->currentBlockIndex == ($this->blockSize));
    }

    protected function fetchBlock($type = self::_NOT_COUNTING)
    {
        $this->results = $this->pdo->query($this->getCurrentBlockQuery($type))
            ->fetchAll(\PDO::FETCH_ASSOC);
    }

    protected function getCurrentBlockQuery($type = self::_COUNTING)
    {
        $query = $this->query . " LIMIT " . $this->blockSize . " OFFSET " .  $this->absoluteIndex;
        if ($type == self::_COUNTING) {
            $query = preg_replace("/SELECT/i", "SELECT SQL_CALC_FOUND_ROWS", $query);
        }
        return ($query);
    }

    public function key()
    {
        return ($this->absoluteIndex);
    }

    public function current()
    {
        return ($this->results[$this->currentBlockIndex]);
    }

    public function rewind()
    {
        if ($this->results == null || $this->absoluteIndex >= $this->blockSize) {
            $this->absoluteIndex = 0;
            $this->currentBlockIndex = 0;
            $this->fetchBlock();
        } else {
            $this->absoluteIndex = 0;
            $this->currentBlockIndex = 0;
        }
    }

    public function valid()
    {
        return ($this->results != null && isset($this->results[$this->currentBlockIndex]));
    }

    public function count()
    {
        if ($this->totalRows === null) {
            $this->fetchBlock(self::_COUNTING);
            $row = $this->pdo->query("SELECT FOUND_ROWS() AS FOUND_ROWS")
                ->fetch(\PDO::FETCH_ASSOC);
            $this->totalRows = $row['FOUND_ROWS'];
        }
        return ($this->totalRows);
    }
}