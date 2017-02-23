<?php
namespace PdoMysqlSelectIterator;

class Iterator implements \Iterator
{
    const BLOCK_SIZE = 1000;
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
     * @var array
     */
    protected $results;

    public function __construct(\PDO $pdo, $query, $blockSize = self::BLOCK_SIZE)
    {
        $this->pdo = $pdo;
        $this->query = $query;
        $this->blockSize = $blockSize;
        $this->currentBlockIndex = 0;
        $this->absoluteIndex = 0;
        $this->results = null;
    }

    public function next()
    {
        $this->absoluteIndex++;
        $this->currentBlockIndex++;
        if ($this->endOfBlockReached()) {
            $this->results = $this->pdo->query($this->getCurrentBlockQuery())->fetchAll();
            $this->currentBlockIndex = 0;
        }
    }

    protected function endOfBlockReached()
    {
        return ($this->currentBlockIndex == ($this->blockSize));
    }

    protected function getCurrentBlockQuery()
    {
        return (
            $this->query . " LIMIT " . $this->blockSize . " OFFSET " .  $this->absoluteIndex
        );
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
            $this->results = $this->pdo->query($this->getCurrentBlockQuery())->fetchAll();
        } else {
            $this->absoluteIndex = 0;
            $this->currentBlockIndex = 0;
        }
    }

    public function valid()
    {
        return ($this->results != null && isset($this->results[$this->currentBlockIndex]));
    }
}