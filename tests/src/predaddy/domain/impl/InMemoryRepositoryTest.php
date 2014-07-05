<?php
/*
 * Copyright (c) 2012-2014 Szurovecz János
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of
 * this software and associated documentation files (the "Software"), to deal in
 * the Software without restriction, including without limitation the rights to
 * use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies
 * of the Software, and to permit persons to whom the Software is furnished to do
 * so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace predaddy\domain\impl;

use PHPUnit_Framework_TestCase;
use predaddy\domain\DefaultAggregateId;

/**
 * @package predaddy\domain\impl
 *
 * @author Szurovecz János <szjani@szjani.hu>
 */
class InMemoryRepositoryTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var InMemoryRepository
     */
    private $repository;

    public function setUp()
    {
        $this->repository = new InMemoryRepository();
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testLoadInvalidId()
    {
        $this->repository->load(new DefaultAggregateId(1, __CLASS__));
    }

    public function testAddAndRemove()
    {
        $aggregateId = new DefaultAggregateId(1, __CLASS__);
        $aggregate = $this->getMockForAbstractClass('\predaddy\domain\AbstractAggregateRoot');
        $aggregate
            ->expects(self::once())
            ->method('getId')
            ->will(self::returnValue($aggregateId));
        $this->repository->save($aggregate);
        $res = $this->repository->load($aggregateId);
        self::assertSame($aggregate, $res);
    }
}
