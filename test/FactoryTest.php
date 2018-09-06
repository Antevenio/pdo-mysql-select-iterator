<?php
namespace PdoMysqlSelectIterator\Test;

use PDO;
use PdoMysqlSelectIterator\LimitIterator;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject;

class FactoryTest extends TestCase
{
    /**
     * @var \PdoMysqlSelectIterator\Factory
     */
    protected $sut;
    /**
     * @var PDO | PHPUnit_Framework_MockObject_MockObject
     */
    protected $pdoMock;

    public function setUp()
    {
        $this->sut = new \PdoMysqlSelectIterator\Factory();
        $this->pdoMock = $this->createMock(PDO::class);

        $this->pdoMock->expects($this->any())
            ->method("query")
            ->will($this->returnValue(new PDOStatement()));
    }

    public function dataProvider() {
        return [
            ["select * from foo", PDOStatement::class],
            ["select * from foo order by a", LimitIterator::class],
            ["select * from foo limit 100", PDOStatement::class],
            ["select * from foo limit 100 order by a", LimitIterator::class],
            ["select a,rand() from foo order by a", PDOStatement::class]
        ];
    }

    /**
     * @dataProvider dataProvider
     */
    public function testShouldReturnTheProperIteratorType($query, $expectedClass)
    {
        $this->assertInstanceOf($expectedClass, $this->sut->create($this->pdoMock, $query, 100));
    }

    /**
     * @dataProvider dataProvider
     */
    public function testShouldReturnNativeIteratorWhenBlockSizeIsZero($query)
    {
        $this->assertInstanceOf(
            PDOStatement::class,
            $this->sut->create($this->pdoMock, $query, 0)
        );
    }
}
