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

namespace predaddy\messagehandling\annotation;

use Doctrine\Common\Annotations\AnnotationReader;
use PHPUnit_Framework_TestCase;
use predaddy\messagehandling\AllMessageHandler;
use predaddy\messagehandling\DefaultFunctionDescriptorFactory;
use predaddy\messagehandling\SimpleMessage;

require_once __DIR__ . '/../SimpleMessage.php';
require_once __DIR__ . '/../SimpleMessageHandler.php';
require_once __DIR__ . '/../AllMessageHandler.php';

/**
 * Description of EventHandlerConfigurationTest
 *
 * @author Szurovecz János <szjani@szjani.hu>
 */
class AnnotatedMessageHandlerDescriptorTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var AnnotatedMessageHandlerDescriptor
     */
    private $config;

    public function setUp()
    {
        AnnotatedMessageHandlerDescriptorFactory::registerAnnotations();
        $handler = new AllMessageHandler();
        $this->config = new AnnotatedMessageHandlerDescriptor(
            $handler->getObjectClass(),
            new AnnotationReader(),
            new DefaultFunctionDescriptorFactory()
        );
    }

    public function testGetHandleMethodFor()
    {
        $message = new SimpleMessage();
        $methods = $this->config->getHandlerMethodsFor($message->getObjectClass());
        self::assertNotNull($methods);
        self::assertEquals(1, count($methods));
    }
}
