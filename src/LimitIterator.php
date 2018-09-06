<?php
namespace PdoMysqlSelectIterator;

use PdoMysqlSelectIterator\Exception\InvalidQueryException;

class LimitIterator implements \Iterator, Iterator
{
    const BLOCK_SIZE = 1000;

    const _NOT_COUNTING = 0;
    const _COUNTING = 1;

    conSt _NO_INITIAL_LIMIT = -1;

    /**
     * @var \PDO
     */
    protected $pdo;

    /**
     * @var string
     */
    protected $query;

    /**
     * @var string
     */
    protected $originalQuery;

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

    protected $rowClass;

    protected $initialOffset;

    protected $initialLimit;

    /**
     * LimitIterator constructor.
     * @param \PDO $pdo
     * @param $query
     * @param int $blockSize
     * @throws InvalidQueryException
     */
    public function __construct(\PDO $pdo, $query, $blockSize = self::BLOCK_SIZE)
    {
        $this->assertValidQuery($query);
        $this->resetAbsoluteIndex();
        $this->resetBlockIndex();
        $this->pdo = $pdo;
        $this->blockSize = $blockSize;
        $this->results = null;
        $this->rowCount = null;
        $this->rowClass = null;
        $this->initialOffset = 0;
        $this->initialLimit = self::_NO_INITIAL_LIMIT;
        $this->setupOffsetAndLimitFromQuery($query);
        $this->originalQuery = $query;
        $this->query = $this->stripLimitFromQuery($query);
    }

    public function setRowClass($rowClass)
    {
        $this->rowClass = $rowClass;
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
        return preg_match('/^\s*SELECT\s+/i',$query);
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
        return $this->currentBlockIndex == $this->blockSize;
    }

    protected function loadNextBlock($type = self::_NOT_COUNTING)
    {
        $this->results = $this->pdo->query($this->getCurrentBlockQuery($type))
            ->fetchAll(\PDO::FETCH_ASSOC);
        $this->resetBlockIndex();
    }

    protected function hasInitialLimit()
    {
        return $this->initialLimit != self::_NO_INITIAL_LIMIT;
    }

    protected function getCurrentBlockQueryLimit()
    {

        $blockSize = $this->blockSize;
        if ($this->hasInitialLimit()) {
            $remainingRows = $this->initialLimit - $this->absoluteIndex;
            if ($remainingRows < $this->blockSize) {
                $blockSize = $remainingRows;
            }
        }
        return $blockSize;
    }

    protected function getCurrentBlockQueryOffset()
    {
        return $this->absoluteIndex + $this->initialOffset;
    }

    protected function getCurrentBlockQuery($type = self::_COUNTING)
    {
        $query =
            $this->query . " LIMIT " . $this->getCurrentBlockQueryLimit() . " OFFSET " .
            $this->getCurrentBlockQueryOffset();
        if ($type == self::_COUNTING) {
            $query = preg_replace("/SELECT/i", "SELECT SQL_CALC_FOUND_ROWS", $query);
        }
        return $query;
    }

    public function key()
    {
        return $this->absoluteIndex;
    }

    public function current()
    {
        $rowdata = $this->results[$this->currentBlockIndex];
        if ($this->rowClass) {
            /** @var Row $row */
            $row = new $this->rowClass();
            $row->hydrate($rowdata);
            return $row;
        }
        return $rowdata;
    }

    public function rewind()
    {
        $onFirstBlock = $this->onFirstBlock();
        $this->resetAbsoluteIndex();
        $this->resetBlockIndex();
        if (!$this->blockLoaded() || !$onFirstBlock) {
            $this->loadNextBlock();
        }
    }

    protected function onFirstBlock()
    {
        return $this->absoluteIndex < $this->blockSize;
    }

    protected function blockLoaded()
    {
        return $this->results != null;
    }

    public function valid()
    {
        return $this->blockLoaded() && $this->currentRowExists();
    }

    protected function currentRowExists()
    {
        return isset($this->results[$this->currentBlockIndex]);
    }

    public function count()
    {
        if (!$this->countDone()) {
            $this->loadNextBlock(self::_COUNTING);
            $this->loadCount();
        }
        return $this->rowCount;
    }

    protected function countDone()
    {
        return $this->rowCount !== null;
    }

    protected function loadCount()
    {
        $row = $this->pdo->query("SELECT FOUND_ROWS() AS FOUND_ROWS")
            ->fetch(\PDO::FETCH_ASSOC);
        $this->rowCount = $row['FOUND_ROWS'];

        if ($this->initialLimit != self::_NO_INITIAL_LIMIT) {
            $this->rowCount =
                ($this->rowCount > $this->initialLimit) ?
                $this->initialLimit :
                $this->rowCount;
        }
    }

    public function close()
    {
        $this->pdo = null;
    }

    protected function setupOffsetAndLimitFromQuery($query)
    {
        if (preg_match('/\s+LIMIT\s+(\d+)\s+OFFSET\s+(\d+)\s*/i', $query, $matches)) {
            $this->initialLimit = $matches[1];
            $this->initialOffset = $matches[2];
            return;
        }
        if (preg_match('/\s+LIMIT\s+(\d+)\s*,\s*(\d+)\s*/i', $query, $matches))
        {
            $this->initialOffset = $matches[1];
            $this->initialLimit = $matches[2];
            return;
        }
        if (preg_match('/\s+LIMIT\s+(\d+)\s*/i', $query, $matches)) {
            $this->initialLimit = $matches[1];
            return;
        }
    }

    protected function stripLimitFromQuery($query)
    {
        return preg_replace('/\s+LIMIT.*$/', '', $query);
    }
}