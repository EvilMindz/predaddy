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

namespace predaddy\commandhandling;

use Exception;
use precore\lang\Object;
use precore\lang\ObjectClass;
use predaddy\domain\DefaultAggregateId;
use predaddy\domain\Repository;
use predaddy\domain\StateHashAware;
use predaddy\messagehandling\annotation\Subscribe;
use predaddy\messagehandling\DeadMessage;
use predaddy\messagehandling\MessageHandlerDescriptorFactory;
use predaddy\messagehandling\util\MessageCallbackClosures;
use predaddy\messagehandling\util\SimpleMessageCallback;

/**
 * The responsibility of this class is to
 *  - obtain the appropriate aggregate from its repository
 *  - pass the incoming commands to the aggregate
 *  - save it through the repository
 *
 * It catches only unhandled commands.
 *
 * @author Janos Szurovecz <szjani@szjani.hu>
 */
final class DirectCommandForwarder extends Object
{
    /**
     * @var Repository
     */
    private $repository;

    /**
     * @var MessageHandlerDescriptorFactory
     */
    private $innerHandlerDescFactory;

    /**
     * @param Repository $repository
     * @param MessageHandlerDescriptorFactory $innerHandlerDescFactory
     */
    public function __construct(Repository $repository, MessageHandlerDescriptorFactory $innerHandlerDescFactory)
    {
        $this->repository = $repository;
        $this->innerHandlerDescFactory = $innerHandlerDescFactory;
    }

    /**
     * @Subscribe
     * @param DeadMessage $deadMessage
     * @return mixed The return value of the last actual handler
     */
    public function catchDeadCommand(DeadMessage $deadMessage)
    {
        $innerMessage = $deadMessage->wrappedMessage();
        ObjectClass::forName(__NAMESPACE__ . '\DirectCommand')->cast($innerMessage);
        return $this->forwardCommand($innerMessage);
    }

    /**
     * @param DirectCommand $command
     * @throws \Exception If the handler throws any
     * @return mixed The return value of the last handler (should be one handler per aggregate)
     */
    protected function forwardCommand(DirectCommand $command)
    {
        $aggregateClass = $command->aggregateClass();
        $aggregateId = $command->aggregateId();
        if ($aggregateId === null) {
            $aggregate = ObjectClass::forName($aggregateClass)->newInstanceWithoutConstructor();
            self::getLogger()->debug('New aggregate [{}] has been created', [$aggregateClass]);
        } else {
            $aggregate = $this->repository->load(new DefaultAggregateId($aggregateId, $aggregateClass));
            self::getLogger()->debug(
                'Aggregate [{}] with ID [{}] has been successfully loaded',
                [$aggregateClass, $aggregateId]
            );
            if ($command instanceof StateHashAware) {
                $aggregate->failWhenStateHashViolation($command->stateHash());
            }
        }
        $forwarderBus = CommandBus::builder()
            ->withIdentifier($aggregateClass)
            ->withHandlerDescriptorFactory($this->innerHandlerDescFactory)
            ->build();
        $forwarderBus->register($aggregate);
        $callback = new SimpleMessageCallback();
        $forwarderBus->post($command, $callback);
        $thrownException = $callback->getException();
        if ($thrownException instanceof Exception) {
            self::getLogger()->debug('Error occurred when command has been applied [{}]', [$command], $thrownException);
            throw $thrownException;
        }
        $this->repository->save($aggregate);
        self::getLogger()->info("Command [{}] has been applied", [$command]);
        return $callback->getResult();
    }
}
