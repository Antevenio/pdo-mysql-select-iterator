<?php
namespace PdoMysqlSelectIterator;

use PdoMysqlSelectIterator\Exception\InvalidQueryException;

class LimitIterator implements \Iterator, Iterator
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
    protected $rowCount;

    /**
     * @var array
     */
    protected $results;

    public function __construct(\PDO $pdo, $query, $blockSize = self::BLOCK_SIZE)
    {
        $this->assertValidQuery($query);
        $this->resetAbsoluteIndex();
        $this->resetBlockIndex();
        $this->pdo = $pdo;
        $this->query = $query;
        $this->blockSize = $blockSize;
        $this->results = null;
        $this->rowCount = null;
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

    protected function resetAbsoluteIndex()
    {
        $this->absoluteIndex = 0;
    }

    protected function resetBlockIndex()
    {
        $this->currentBlockIndex = 0;
    }

    public function next()
    {
        $this->incrementAbsoluteIndex();
        $this->incrementBlockIndex();
        if ($this->endOfBlockReached()) {
            $this->loadNextBlock();
        }
    }

    protected function incrementAbsoluteIndex()
    {
        $this->absoluteIndex++;
    }

    protected function incrementBlockIndex()
    {
        $this->currentBlockIndex++;
    }

    protected function endOfBlockReached()
    {
        return ($this->currentBlockIndex == ($this->blockSize));
    }

    protected function loadNextBlock($type = self::_NOT_COUNTING)
    {
        $this->results = $this->pdo->query($this->getCurrentBlockQuery($type))
            ->fetchAll(\PDO::FETCH_ASSOC);
        $this->resetBlockIndex();
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
        $onFirstBlock = $this->onFirstBlock();
        $this->resetAbsoluteIndex();
        if (!$this->blockLoaded() || !$onFirstBlock) {
            $this->loadNextBlock();
        }
    }

    protected function onFirstBlock()
    {
        return ($this->absoluteIndex < $this->blockSize);
    }

    protected function blockLoaded()
    {
        return ($this->results != null);
    }

    public function valid()
    {
        return ($this->blockLoaded() && $this->currentRowExists());
    }

    protected function currentRowExists()
    {
        return (isset($this->results[$this->currentBlockIndex]));
    }

    public function count()
    {
        if (!$this->countDone()) {
            $this->loadNextBlock(self::_COUNTING);
            $this->loadCount();
        }
        return ($this->rowCount);
    }

    protected function countDone()
    {
        return ($this->rowCount !== null);
    }

    protected function loadCount()
    {
        $row = $this->pdo->query("SELECT FOUND_ROWS() AS FOUND_ROWS")
            ->fetch(\PDO::FETCH_ASSOC);
        $this->rowCount = $row['FOUND_ROWS'];
    }

    public function close()
    {
        $this->pdo = null;
    }
}