<?php
/*
 * Copyright (c) 2013 Szurovecz János
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

namespace predaddy\domain;

use PHPUnit_Framework_TestCase;
use predaddy\eventhandling\EventBus;
use predaddy\eventhandling\EventFunctionDescriptorFactory;
use predaddy\messagehandling\annotation\AnnotatedMessageHandlerDescriptorFactory;
use predaddy\messagehandling\DefaultFunctionDescriptorFactory;
use SplObjectStorage;

/**
 * Description of AggregateRootTest
 *
 * @author Szurovecz János <szjani@szjani.hu>
 */
class AggregateRootTest extends DomainTestCase
{
    public function testCallHandleMethod()
    {
        $user = new User();
        self::assertEquals(User::DEFAULT_VALUE, $user->value);

        $user->increment();
        $events = $this->getAndClearRaisedEvents();
        self::assertEquals(2, $user->value);
        self::assertTrue($events->valid());
        self::assertEquals($events->current()->getAggregateId(), $user->getId());
    }

    public function testEquals()
    {
        $user = new User();
        $clone = $this->getMock(User::className(), ['getId'], [], '', false);
        $clone
            ->expects(self::once())
            ->method('getId')
            ->will(self::returnValue($user->getId()));
        self::assertTrue($user->equals($clone));
    }

    public function testToString()
    {
        $user = new User();
        self::assertStringStartsWith(User::className(), $user->toString());
    }

    /**
     * @test
     */
    public function stateHashMatching()
    {
        $user = new User();
        $events = $this->getAndClearRaisedEvents();
        self::assertCount(1, $events);
        $event = $events[0];
        /* @var $event DomainEvent */
        self::assertEquals($event->getStateHash(), $user->getStateHash());
        $user->failWhenStateHashViolation($event->getStateHash());
    }

    /**
     * @test
     * @expectedException \UnexpectedValueException
     */
    public function stateHashViolationShouldThrowException()
    {
        $user = new User();
        $events = $this->getAndClearRaisedEvents();
        self::assertCount(1, $events);
        $event = $events[0];
        /* @var $event DomainEvent */
        self::assertEquals($event->getStateHash(), $user->getStateHash());

        $user->increment();
        $events = $this->getAndClearRaisedEvents();
        self::assertCount(1, $events);
        $event = $events[0];
        $user->failWhenStateHashViolation($event->getStateHash());

        $user->failWhenStateHashViolation('invalid');
    }
}
