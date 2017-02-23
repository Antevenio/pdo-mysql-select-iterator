<?php
use PdoMysqlSelectIterator\Iterator;
use PHPUnit\Framework\TestCase;

class IteratorTest extends TestCase
{
    protected $query;
    /**
     * @var \PDO | \PHPUnit_Framework_MockObject_MockObject
     */
    protected $pdo;
    /**
     * @var Iterator
     */
    protected $sut;
    /**
     * @var int
     */
    protected $blockSize;

    public function setUp()
    {
        $this->query = "SELECT * FROM TABLE";
        $this->pdo = $this->createMock(\PDO::class);
        $this->blockSize = 3;
        $this->sut = new Iterator($this->pdo, $this->query, $this->blockSize);
    }

    protected function setPdoQueryExpectations($queryParams)
    {
        $j = 0;
        foreach($queryParams as $params) {
            $blockSize = $params[0];
            $offset = $params[1];
            $returnedData = $params[2];

            $pdoStatement = $this->createMock(PDOStatement::class);

            $this->pdo->expects($this->at($j++))
                ->method("query")
                ->with($this->equalTo(
                    "SELECT * FROM TABLE LIMIT $blockSize OFFSET $offset"))
                ->will($this->returnValue($pdoStatement));

            $pdoStatement->expects($this->once())
                ->method("fetchAll")
                ->will($this->returnValue($returnedData));
        }
    }

    public function testFirstRewindShouldRunLimitQuery()
    {
        $this->setPdoQueryExpectations(
            [
                [3, 0, [
                    ["a" => "a1", "b" => "b1"],
                    ["a" => "a2", "b" => "b2"],
                    ["a" => "a3", "b" => "b3"]]],
            ]
        );
        $this->sut->rewind();
    }

    public function testRewindShouldNotRunQueryIfAlreadyOnFirstBlock()
    {
        $this->setPdoQueryExpectations(
            [
                [3, 0, [
                    ["a" => "a1", "b" => "b1"],
                    ["a" => "a2", "b" => "b2"],
                    ["a" => "a3", "b" => "b3"]]],
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
                [3, 0, [
                    ["a" => "a1", "b" => "b1"],
                    ["a" => "a2", "b" => "b2"],
                    ["a" => "a3", "b" => "b3"]]],
                [3, 3, [
                    ["a" => "a4", "b" => "b4"],
                    ["a" => "a5", "b" => "b5"],
                    ["a" => "a6", "b" => "b6"]]],
                [3, 0, [
                    ["a" => "a1", "b" => "b1"],
                    ["a" => "a2", "b" => "b2"],
                    ["a" => "a3", "b" => "b3"]]]
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
                [3, 0, [
                    ["a" => "a1", "b" => "b1"],
                    ["a" => "a2", "b" => "b2"],
                    ["a" => "a3", "b" => "b3"]]],
                [3, 3, [
                    ["a" => "a4", "b" => "b4"],
                    ["a" => "a5", "b" => "b5"],
                    ["a" => "a6", "b" => "b6"]]],
                [3, 6, [
                    ["a" => "a7", "b" => "b7"],
                    ["a" => "a8", "b" => "b8"],
                    ["a" => "a9", "b" => "b9"]]]
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
                [3, 0, [
                    ["a" => "a1", "b" => "b1"],
                    ["a" => "a2", "b" => "b2"],
                    ["a" => "a3", "b" => "b3"]]],
                [3, 3, [
                    ["a" => "a4", "b" => "b4"],
                    ["a" => "a5", "b" => "b5"],
                    ["a" => "a6", "b" => "b6"]]]
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
                [3, 0, [
                    ["a" => "a1", "b" => "b1"],
                    ["a" => "a2", "b" => "b2"],
                    ["a" => "a3", "b" => "b3"]]]
            ]
        );
        $this->sut->rewind();
        $this->assertEquals(0, $this->sut->key());
        $this->sut->next();
        $this->sut->rewind();
        $this->assertEquals(0, $this->sut->key());
    }

    public function testCurrentShouldReturnCurrentRow()
    {
        $this->setPdoQueryExpectations(
            [
                [3, 0, [
                    ["a" => "a1", "b" => "b1"],
                    ["a" => "a2", "b" => "b2"],
                    ["a" => "a3", "b" => "b3"]]],
                [3, 3, [
                    ["a" => "a4", "b" => "b4"],
                    ["a" => "a5", "b" => "b5"],
                    ["a" => "a6", "b" => "b6"]]],
                [3, 6, [
                    ["a" => "a7", "b" => "b7"],
                    ["a" => "a8", "b" => "b8"],
                    ["a" => "a9", "b" => "b9"]]]
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

    public function testValidShouldReturnTrueWhenRowExists()
    {
        $this->setPdoQueryExpectations(
            [
                [3, 0, [
                    ["a" => "a1", "b" => "b1"],
                    ["a" => "a2", "b" => "b2"],
                    ["a" => "a3", "b" => "b3"]]],
                [3, 3, [
                    ["a" => "a4", "b" => "b4"],
                    ["a" => "a5", "b" => "b5"],
                    ["a" => "a6", "b" => "b6"]]],
            ]
        );
        $this->sut->rewind();
        for ($i = 0; $i < 3; $i++) {
            $this->assertTrue($this->sut->valid());
            $this->sut->next();
        }
    }

    public function testValidShouldReturnFalseWhenRowDoesntExist()
    {
        $this->setPdoQueryExpectations(
            [
                [3, 0, [
                    ["a" => "a1", "b" => "b1"]]]
            ]
        );
        $this->sut->rewind();
        $this->sut->next();
        $this->assertFalse($this->sut->valid());
    }
}