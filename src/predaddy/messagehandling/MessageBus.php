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

namespace predaddy\messagehandling;

use Closure;
use Iterator;

/**
 * @author Szurovecz János <szjani@szjani.hu>
 */
interface MessageBus
{
    /**
     * Post a message on this bus. It is dispatched to all subscribed handlers.
     *
     * @param Message $message
     * @return void
     */
    public function post(Message $message);

    /**
     * @param Iterator $interceptors HandlerInterceptor instances
     * @return void
     */
    public function setInterceptors(Iterator $interceptors);

    /**
     * Register the given handler to this bus. When registered, it will receive all messages posted to this bus.
     *
     * @param mixed $handler
     */
    public function register($handler);

    /**
     * Un-register the given handler to this bus.
     * When unregistered, it will no longer receive messages posted to this bus.
     *
     * @param object $handler
     */
    public function unregister($handler);

    /**
     * @param callable $closure
     */
    public function registerClosure(Closure $closure);

    /**
     * @param callable $closure
     */
    public function unregisterClosure(Closure $closure);
}