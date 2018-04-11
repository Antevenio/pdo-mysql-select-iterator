<?php
namespace PdoMysqlSelectIterator\Test;

use PdoMysqlSelectIterator\LimitIterator;
use PDOStatement;
use PHPUnit\Framework\TestCase;

class IteratorTest extends TestCase
{
    const NOT_COUNTING = 0;
    const COUNTING = 1;

    protected $query;
    /**
     * @var \PDO | \PHPUnit_Framework_MockObject_MockObject
     */
    protected $pdo;
    /**
     * @var LimitIterator
     */
    protected $sut;
    /**
     * @var int
     */
    protected $blockSize;

    const DATA_BLOCKS = [
        [
            ["a" => "a1", "b" => "b1"],
            ["a" => "a2", "b" => "b2"],
            ["a" => "a3", "b" => "b3"]
        ],
        [
            ["a" => "a4", "b" => "b4"],
            ["a" => "a5", "b" => "b5"],
            ["a" => "a6", "b" => "b6"]
        ],
        [
            ["a" => "a7", "b" => "b7"],
            ["a" => "a8", "b" => "b8"],
            ["a" => "a9", "b" => "b9"]
        ]
    ];

    public function setUp()
    {
        $this->query = "SELECT * FROM TABLE";
        $this->pdo = $this->createMock(\PDO::class);
        $this->blockSize = 3;
        $this->sut = new LimitIterator($this->pdo, $this->query, $this->blockSize);
    }

    protected function setPdoQueryExpectations(
        $queryParams, $type = self::NOT_COUNTING, $returnedCount = 0
    )
    {
        $j = 0;
        $selectString = "SELECT";
        if ($type == self::COUNTING) {
            $selectString = "SELECT SQL_CALC_FOUND_ROWS";
        }
        foreach($queryParams as $params) {
            $blockSize = $params[0];
            $offset = $params[1];
            $returnedData = $params[2];

            $pdoStatement = $this->createMock(PDOStatement::class);

            $this->pdo->expects($this->at($j++))
                ->method("query")
                ->with($this->equalTo(
                    "$selectString * FROM TABLE LIMIT $blockSize OFFSET $offset"))
                ->will($this->returnValue($pdoStatement));

            $pdoStatement->expects($this->once())
                ->method("fetchAll")
                ->with($this->equalTo(\PDO::FETCH_ASSOC))
                ->will($this->returnValue($returnedData));
        }
        if ($type == self::COUNTING) {
            $pdoStatement = $this->createMock(PDOStatement::class);
            $this->pdo->expects($this->at($j))
                ->method("query")
                ->with($this->equalTo(
                    "SELECT FOUND_ROWS() AS FOUND_ROWS"))
                ->will($this->returnValue($pdoStatement));

            $pdoStatement->expects($this->once())
                ->method("fetch")
                ->with($this->equalTo(\PDO::FETCH_ASSOC))
                ->will($this->returnValue(["FOUND_ROWS" => $returnedCount]));
        }
    }

    public function testFirstRewindShouldRunLimitQuery()
    {
        $this->setPdoQueryExpectations(
            [
                [3, 0, self::DATA_BLOCKS[0]],
            ]
        );
        $this->sut->rewind();
    }

    public function testRewindShouldNotRunQueryIfAlreadyOnFirstBlock()
    {
        $this->setPdoQueryExpectations(
            [
                [3, 0, self::DATA_BLOCKS[0]],
            ]
        );
        $this->sut->rewind();
        $this->pdo->expects($this->never())
            ->method("query");
        $this->sut->rewind();
    }

    public function testRewindShouldRunQueryIfPastFirstBlock()
    {
        $this->setPdoQueryExpectations(
            [
                [3, 0, self::DATA_BLOCKS[0]],
                [3, 3, self::DATA_BLOCKS[1]],
                [3, 0, self::DATA_BLOCKS[0]]
            ]
        );
        $this->sut->rewind();
        for ($i = 0; $i < 3; $i++) {
            $this->sut->next();
        }
        $this->sut->rewind();
    }

    public function testNextShouldRunQueriesWhenReachingNewBlocks()
    {
        $this->setPdoQueryExpectations(
            [
                [3, 0, self::DATA_BLOCKS[0]],
                [3, 3, self::DATA_BLOCKS[1]],
                [3, 6, self::DATA_BLOCKS[2]]
            ]
        );
        $this->sut->rewind();
        for ($i = 0; $i < 6; $i++) {
            $this->sut->next();
        }
    }


    public function testKeyShouldReturnCurrentIndex()
    {
        $this->setPdoQueryExpectations(
            [
                [3, 0, self::DATA_BLOCKS[0]],
                [3, 3, self::DATA_BLOCKS[1]]
            ]
        );
        $this->sut->rewind();
        for ($i = 0; $i < 5; $i++) {
            $this->assertEquals($i, $this->sut->key());
            $this->sut->next();
        }
    }

    public function testRewindShouldResetCurrentIndex()
    {
        $this->setPdoQueryExpectations(
            [
                [3, 0, self::DATA_BLOCKS[0]]
            ]
        );
        $this->sut->rewind();
        $this->assertEquals(0, $this->sut->key());
        $this->sut->next();
        $this->assertEquals(1, $this->sut->key());
        $this->sut->next();
        $this->assertEquals(2, $this->sut->key());
        $this->sut->rewind();
        $this->assertEquals(0, $this->sut->key());
    }

    public function testCurrentShouldReturnCurrentRow()
    {
        $this->setPdoQueryExpectations(
            [
                [3, 0, self::DATA_BLOCKS[0]],
                [3, 3, self::DATA_BLOCKS[1]],
                [3, 6, self::DATA_BLOCKS[2]]
            ]
        );
        $this->sut->rewind();
        for ($i = 0; $i < 8; $i++) {
            $this->assertEquals(
                ["a" => "a".($i+1), "b" => "b".($i+1)], $this->sut->current()
            );
            $this->sut->next();
        }
    }

    public function testCurrentShouldReturnAnHydratedRowClassIfDefined()
    {
        $this->sut->setRowClass(TestRow::class);
        $this->setPdoQueryExpectations(
            [
                [3, 0, self::DATA_BLOCKS[0]],
                [3, 3, self::DATA_BLOCKS[1]]
            ]
        );
        $this->sut->rewind();
        for ($i = 0; $i < 3; $i++) {
            $this->assertEquals(
                ["a" => "a".($i+1), "b" => "b".($i+1)], $this->sut->current()->getRow()
            );
            $this->sut->next();
        }
    }

    public function testValidShouldReturnTrueWhenCurrentRowExists()
    {
        $this->setPdoQueryExpectations(
            [
                [3, 0, self::DATA_BLOCKS[0]],
                [3, 3, self::DATA_BLOCKS[1]]
            ]
        );
        $this->sut->rewind();
        for ($i = 0; $i < 3; $i++) {
            $this->assertTrue($this->sut->valid());
            $this->sut->next();
        }
    }

    public function testValidShouldReturnFalseWhenCurrentRowDoesntExist()
    {
        $this->setPdoQueryExpectations(
            [
                [3, 0, [self::DATA_BLOCKS[0][0]]]
            ]
        );
        $this->sut->rewind();
        $this->sut->next();
        $this->assertFalse($this->sut->valid());
    }

    public function testConstructorShouldThrowExcepcionOnInvalidQuery()
    {
        $this->expectException(\PdoMysqlSelectIterator\Exception\InvalidQueryException::class);
        new LimitIterator($this->pdo, "I AM NOT A SELECT", $this->blockSize);
    }

    public function testCountShouldCount()
    {
        $numRows = 99;
        $this->setPdoQueryExpectations(
            [
                [3, 0, self::DATA_BLOCKS[0]]
            ], self::COUNTING, $numRows
        );
        $this->assertEquals($numRows, $this->sut->count());
    }

    public function testCountShouldNotCountIfAlreadyDid()
    {
        $numRows = 99;
        $this->setPdoQueryExpectations(
            [
                [3, 0, self::DATA_BLOCKS[0]]
            ], self::COUNTING, $numRows
        );
        $this->assertEquals($numRows, $this->sut->count());
        $this->pdo->expects($this->never())
            ->method("query");
        $this->assertEquals($numRows, $this->sut->count());
    }
}