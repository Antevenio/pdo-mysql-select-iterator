<?php
namespace PdoMysqlSelectIterator\Test;

use PdoMysqlSelectIterator\Exception\InvalidQueryException;
use PdoMysqlSelectIterator\RedisIterator;
use PdoMysqlSelectIterator\UniqueIdGenerator;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use Predis\Client;

class RedisIteratorTest extends TestCase
{
    protected $query;
    /**
     * @var \PDO | \PHPUnit_Framework_MockObject_MockObject
     */
    protected $pdo;
    /**
     * @var RedisIterator
     */
    protected $sut;
    /**
     * @var Client | \PHPUnit_Framework_MockObject_MockObject
     */
    protected $redis;
    /**
     * @var UniqueIdGenerator | \PHPUnit_Framework_MockObject_MockObject
     */
    protected $idGenerator;

    const DATA = [
        ["a" => "a1", "b" => "b1"],
        ["a" => "a2", "b" => "b2"],
        ["a" => "a3", "b" => "b3"]
    ];

    const UNIQUE_ID = "someUniqueId";

    public function setUp()
    {
        $this->query = "SELECT * FROM TABLE";
        $this->pdo = $this->createMock(\PDO::class);
        $this->redis = $this->createPartialMock(
            Client::class,
            ["del", "rpush", "lpop"]
        );
        $this->idGenerator = $this->createMock(UniqueIdGenerator::class);
        $this->idGenerator->expects($this->once())
            ->method("getUniqueId")
            ->will($this->returnValue(self::UNIQUE_ID));
        $this->buildSut();
    }

    protected function buildSut()
    {
        $this->sut = new RedisIterator(
            $this->pdo, $this->query, $this->redis, $this->idGenerator
        );
    }

    public function testConstructShouldThrowExceptionOnInvalidQuery()
    {
        $this->query = "not a select statement";
        $this->expectException(InvalidQueryException::class);
        $this->buildSut();
    }

    protected function setUpPdoQueryExpectations()
    {
        $statement = $this->createMock(PDOStatement::class);
        $this->pdo->expects($this->once())
            ->method("query")
            ->with($this->query)
            ->will($this->returnValue($statement));
        $statement->method("fetch")
            ->with($this->equalTo(\PDO::FETCH_ASSOC))
            ->willReturnOnConsecutiveCalls(...self::DATA);
    }

    protected function setUpQueryCacheExpectations()
    {
        $this->setUpPdoQueryExpectations();

        $consecutiveRpushArgs = [];
        foreach (self::DATA as $row) {
            $consecutiveRpushArgs[] = [
                self::UNIQUE_ID, [serialize($row)]
            ];
        }

        $this->redis->expects($this->exactly(count(self::DATA)))
            ->method("rpush")
            ->withConsecutive(...$consecutiveRpushArgs);
    }

    protected function setUpCacheReadingExpectations()
    {
        $this->redis->expects($this->exactly(count(self::DATA)))
            ->method("lpop")
            ->with($this->equalTo(self::UNIQUE_ID))
            ->willReturnOnConsecutiveCalls(...array_map("serialize", self::DATA));
    }

    protected function setUpCleanCacheExpectations()
    {
        $this->redis->expects($this->once())
            ->method("del")
            ->with($this->equalTo([self::UNIQUE_ID]));
    }

    public function testRewindShouldCleanAPreviousCache()
    {
        $this->setUpPdoQueryExpectations();
        $this->setUpCleanCacheExpectations();
        $this->sut->rewind();
    }

    public function testRewindShouldCacheQueryResults()
    {
        $this->setUpQueryCacheExpectations();
        $this->sut->rewind();
    }

    public function testCountShouldCacheQueryResultsIfNotPreviouslyCached()
    {
        $this->setUpQueryCacheExpectations();
        $this->sut->count();
    }

    public function testCountShouldNotCacheQueryResultIfAlreadyCached()
    {
        $this->setUpQueryCacheExpectations();
        $this->sut->rewind();
        $this->sut->count();
    }

    public function testCountShouldReturnActualCount()
    {
        $this->setUpQueryCacheExpectations();
        $this->assertEquals(count(self::DATA), $this->sut->count());
    }

    public function testForeachLoopShouldReturnOriginalResults()
    {
        $this->setUpQueryCacheExpectations();
        $this->setUpCacheReadingExpectations();
        $index = 0;
        foreach ($this->sut as $item)
        {
            $this->assertEquals($index, $this->sut->key());
            $this->assertEquals(self::DATA[$index], $item);
            $index++;
        }
        $this->assertEquals(count(self::DATA), $index);
    }

    public function testCloseShouldCleanTheCache()
    {
        $this->setUpCleanCacheExpectations();
        $this->sut->close();
    }
}