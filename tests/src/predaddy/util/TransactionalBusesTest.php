<?php
/*
 * Copyright (c) 2012-2014 Janos Szurovecz
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

namespace predaddy\util;

use Exception;
use PHPUnit_Framework_TestCase;
use predaddy\commandhandling\DirectCommandBus;
use predaddy\commandhandling\SimpleCommand;
use predaddy\eventhandling\SimpleEvent;
use predaddy\fixture\BaseEvent;
use predaddy\fixture\BaseEvent2;
use predaddy\MessageHandler;
use RuntimeException;
use trf4php\TransactionException;

/**
 * @package predaddy\util
 *
 * @author Janos Szurovecz <szjani@szjani.hu>
 */
class TransactionalBusesTest extends PHPUnit_Framework_TestCase
{
    private $transactionManager;

    /**
     * @var TransactionalBuses
     */
    private $buses;

    /**
     * @var MessageHandler
     */
    private $eventHandler;

    protected function setUp()
    {
        $this->transactionManager = $this->getMock('\trf4php\TransactionManager');
        $this->buses = TransactionalBusesBuilder::create($this->transactionManager)->build();
        $this->eventHandler = new MessageHandler();
        $this->buses->eventBus()->register($this->eventHandler);
    }

    /**
     * @test
     */
    public function collectedEventsMustBeSent()
    {
        $commandBus = $this->buses->commandBus();
        $event = new SimpleEvent();
        $commandBus->registerClosure(
            function (SimpleCommand $command) use ($event) {
                $this->buses->eventBus()->post($event);
            }
        );
        $this->transactionManager
            ->expects(self::once())
            ->method('commit');
        $commandBus->post(new SimpleCommand());
        self::assertTrue($this->eventHandler->called(1));
    }

    /**
     * @test
     */
    public function collectedEventsMustBeDeletedIfCommandHandlerFails()
    {
        $commandBus = $this->buses->commandBus();
        $event = new SimpleEvent();
        $commandBus->registerClosure(
            function (SimpleCommand $command) use ($event) {
                $this->buses->eventBus()->post($event);
                throw new RuntimeException('Expected exception');
            }
        );
        $this->transactionManager
            ->expects(self::never())
            ->method('commit');
        $this->transactionManager
            ->expects(self::once())
            ->method('rollback');

        $commandBus->post(new SimpleCommand());
        self::assertTrue($this->eventHandler->neverCalled());
    }

    /**
     * @test
     */
    public function collectedEventsMustBeDeletedIfCommitFails()
    {
        $commandBus = $this->buses->commandBus();
        $event = new SimpleEvent();
        $commandBus->registerClosure(
            function (SimpleCommand $command) use ($event) {
                $this->buses->eventBus()->post($event);
            }
        );
        $this->transactionManager
            ->expects(self::once())
            ->method('commit')
            ->will(self::throwException(new TransactionException()));
        try {
            $commandBus->post(new SimpleCommand());
            self::fail('Exception should be throws');
        } catch (TransactionException $e) {
        }
        self::assertTrue($this->eventHandler->neverCalled());
    }

    /**
     * @test
     */
    public function shouldCallCommandInterceptorBeforeTransaction()
    {
        $interceptor = $this->getMock('\predaddy\messagehandling\DispatchInterceptor');
        $interceptor
            ->expects(self::once())
            ->method('invoke');
        $this->transactionManager
            ->expects(self::never())
            ->method('beginTransaction');

        $buses = TransactionalBusesBuilder::create($this->transactionManager)
            ->interceptCommandsOutsideTransaction([$interceptor])
            ->build();
        $buses->commandBus()->post(new SimpleCommand());
    }

    /**
     * @test
     */
    public function shouldCallCommandInterceptorWithinTransaction()
    {
        $interceptor = $this->getMock('\predaddy\messagehandling\DispatchInterceptor');
        $interceptor
            ->expects(self::once())
            ->method('invoke');
        $this->transactionManager
            ->expects(self::once())
            ->method('beginTransaction');

        $buses = TransactionalBusesBuilder::create($this->transactionManager)
            ->interceptCommandsWithinTransaction([$interceptor])
            ->build();
        $buses->commandBus()->post(new SimpleCommand());
    }

    /**
     * @test
     */
    public function shouldCallEventInterceptorWithinTransaction()
    {
        $interceptor = $this->getMock('\predaddy\messagehandling\DispatchInterceptor');
        $interceptor
            ->expects(self::once())
            ->method('invoke');

        $buses = TransactionalBusesBuilder::create($this->transactionManager)
            ->interceptEventsWithinTransaction([$interceptor])
            ->build();

        $buses->commandBus()->registerClosure(
            function (SimpleCommand $command) use ($buses) {
                $buses->eventBus()->post(new SimpleEvent());
                throw new Exception();
            }
        );
        $buses->commandBus()->post(new SimpleCommand());
    }

    /**
     * @test
     */
    public function shouldNotCallEventInterceptorOutsideTransaction()
    {
        $interceptor = $this->getMock('\predaddy\messagehandling\DispatchInterceptor');
        $interceptor
            ->expects(self::never())
            ->method('invoke');

        $buses = TransactionalBusesBuilder::create($this->transactionManager)
            ->interceptEventsOutsideTransaction([$interceptor])
            ->build();

        $buses->commandBus()->registerClosure(
            function (SimpleCommand $command) use ($buses) {
                $buses->eventBus()->post(new SimpleEvent());
                throw new Exception();
            }
        );
        $buses->commandBus()->post(new SimpleCommand());
    }

    /**
     * @test
     */
    public function shouldCreateDirectCommandBus()
    {
        $buses = TransactionalBusesBuilder::create($this->transactionManager)
            ->withRepository($this->getMock('\predaddy\domain\Repository'))
            ->build();
        $commandBus = $buses->commandBus();
        self::assertInstanceOf(DirectCommandBus::className(), $commandBus);
    }

    /**
     * @test
     */
    public function shouldStopBlockingBeforeFlushing()
    {
        $commandBus = $this->buses->commandBus();
        $eventBus = $this->buses->eventBus();
        $secondHandlerCalled = false;

        $commandBus->registerClosure(
            function (SimpleCommand $command) {
                $this->buses->eventBus()->post(new BaseEvent());
            }
        );

        $eventBus->registerClosure(
            function (BaseEvent $event) use ($eventBus) {
                $eventBus->post(new BaseEvent2());
            }
        );
        $eventBus->registerClosure(
            function (BaseEvent2 $event2) use (&$secondHandlerCalled) {
                $secondHandlerCalled = true;
            }
        );

        $commandBus->post(new SimpleCommand());
        self::assertTrue($secondHandlerCalled);
    }
}
