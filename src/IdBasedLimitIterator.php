<?php
namespace PdoMysqlSelectIterator;

use PdoMysqlSelectIterator\Exception\InvalidQueryException;
use PHPSQLParser\PHPSQLCreator;
use PHPSQLParser\PHPSQLParser;

class IdBasedLimitIterator implements \Iterator, Iterator
{
    const ID_FIELD_ALIAS = '__iterator_id__';
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

    protected $idField;

    protected $lastIdValue;

    protected $orderDirection;

    protected $parsedQuery;

    /**
     * LimitIterator constructor.
     * @param \PDO $pdo
     * @param $query
     * @param int $blockSize
     * @throws InvalidQueryException
     */
    public function __construct(\PDO $pdo, $query, $blockSize = self::BLOCK_SIZE)
    {
        $this->query = $query;
        $this->originalQuery = $query;
        $this->parseQuery();
        $this->resetAbsoluteIndex();
        $this->resetBlockIndex();
        $this->pdo = $pdo;
        $this->blockSize = $blockSize;
        $this->results = null;
        $this->rowCount = null;
        $this->rowClass = null;
        $this->initialOffset = 0;
    }

    protected function parseQuery()
    {
        $this->parsedQuery = (new PHPSQLParser())->parse($this->query);
        $this->assertValidQuery();
        $this->idField = $this->parsedQuery['ORDER'][0]['base_expr'];
        $this->orderDirection = $this->parsedQuery['ORDER'][0]['direction'];
    }

    public function setRowClass($rowClass)
    {
        $this->rowClass = $rowClass;
    }

    /**
     * @param $query
     * @throws InvalidQueryException
     */
    protected function assertValidQuery()
    {
        if (!$this->isAValidSelect()) {
            throw new InvalidQueryException(
                "The query provided is not a valid SELECT statement"
            );
        }
        if (!$this->hasOrderByClause()) {
            throw new InvalidQueryException(
                "The query provided does not have an ORDER BY clause"
            );
        }
        if (!$this->orderByClauseHasJustOneColumn($this->query)) {
            throw new InvalidQueryException(
                "The query provided has multiple fields in the order clause"
            );
        }
        if ($this->hasLimitClause()) {
            throw new InvalidQueryException(
                "The query provided already have a LIMIT clause"
            );
        }
    }

    protected function orderByClauseHasJustOneColumn($query)
    {
        return (isset($this->parsedQuery['ORDER']) && count($this->parsedQuery['ORDER']) == 1);
    }

    protected function isAValidSelect()
    {
        return isset($this->parsedQuery['SELECT']);
    }

    protected function hasOrderByClause()
    {
        return isset($this->parsedQuery['ORDER']);
    }

    protected function hasLimitClause()
    {
        return isset($this->parsedQuery['LIMIT']);
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
        $this->lastIdValue = $this->results[count($this->results)-1][self::ID_FIELD_ALIAS];
    }

    protected function getCurrentBlockQueryLimit()
    {
        return $this->blockSize;
    }

    protected function getCurrentBlockQuery($type = self::_COUNTING)
    {
        if (!$this->lastIdValue) {
            return $this->query;
        }

        $parsedQuery = $this->parsedQuery;

        if (isset($parsedQuery['WHERE'])) {
            $parsedQuery['WHERE'][] = [
                'expr_type' => 'operator',
                'base_expr' => 'and'
            ];
        }

        $parsedQuery['WHERE'][] = [
            'expr_type' => 'colref',
            'base_expr' => $this->idField
        ];

        $parsedQuery['WHERE'][] = [
            'expr_type' => 'operator',
            'base_expr' => $this->orderDirection == 'DESC' ? '<' : '>'
        ];

        $parsedQuery['WHERE'][] = [
            'expr_type' => 'const',
            'base_expr' => $this->lastIdValue
        ];

        $parsedQuery['LIMIT'] = [
            'offset' => 0,
            'rowcount' => $this->getCurrentBlockQueryLimit()
        ];

        $parsedQuery['SELECT'][count($parsedQuery['SELECT']) - 1]['delim'] = ',';

        $parsedQuery['SELECT'][] = [
            'expr_type' => 'colref',
            'base_expr' => $this->idField,
            'alias' => [
                'name' => self::ID_FIELD_ALIAS
            ]
        ];

        if ($type == self::_COUNTING) {
            $parsedQuery['SELECT'] = array_merge(
                [
                    'expr_type' => 'reserved',
                    'base_expr' => 'SQL_CALC_FOUND_ROWS',
                    'delim' => ' '
                ],
                $parsedQuery['SELECT']
            );
        }

        return (new PHPSQLCreator())->create($parsedQuery);
    }

    public function key()
    {
        return $this->absoluteIndex;
    }

    public function current()
    {
        $rowdata = $this->results[$this->currentBlockIndex];
        unset($rowdata[self::ID_FIELD_ALIAS]);
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
        $this->lastIdValue = null;
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
    }

    public function close()
    {
        $this->pdo = null;
    }
}
