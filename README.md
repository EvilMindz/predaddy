predaddy
========
[![Latest Stable Version](https://poser.pugx.org/predaddy/predaddy/v/stable.png)](https://packagist.org/packages/predaddy/predaddy)

master: [![Build Status](https://travis-ci.org/szjani/predaddy.png?branch=master)](https://travis-ci.org/szjani/predaddy) [![Coverage Status](https://coveralls.io/repos/szjani/predaddy/badge.png?branch=master)](https://coveralls.io/r/szjani/predaddy?branch=master)
1.2: [![Build Status](https://travis-ci.org/szjani/predaddy.png?branch=1.2)](https://travis-ci.org/szjani/predaddy) [![Coverage Status](https://coveralls.io/repos/szjani/predaddy/badge.png?branch=1.2)](https://coveralls.io/r/szjani/predaddy?branch=1.2)

It is a library which gives you some usable classes to be able to use common DDD patterns.
You can find some examples in the [sample directory](https://github.com/szjani/predaddy/tree/master/sample).

MessageBus
----------

MessageBus provides a general interface for message handling. The basic concept is that message handlers can
be registered to the bus which forwards each incoming messages to the appropriate handler. Message handlers
can be either objects or closures.

SimpleMessageBus is a basic implementation of the MessageBus interface. Currently, all other MessageBus implementations extend this class.

If you use CQRS, then I highly recommend to use the pre-configured `AnnotationBasedEventBus` and `AnnotationBasedCommandBus` classes.
For more information, please scroll down.

### Handler methods/functions

Predaddy is quite configurable, but it has several default behaviours. Handler functions/methods should have one parameter with typehint.
The typehint defines which `Message` objects can be handled, by default. If you want to handle all `Message` objects,
you just have to use `Message` typehint. This kind of solution provides an easy way to use and distinguish a huge amount of
message classes. Interface and abstract class typehints also work as expected.

### Annotations

You can use your own handler method scanning/defining process, however the system does support annotation based configuration.
It means that you just have to mark handler methods in your handler classes with `@Subject` annotation. When you register an instance
of this class, predaddy is automatically finding these methods.

### Interceptors

It's possible to extend bus behaviour when messages are being dispatched to message handlers. `HandlerInterceptor` objects wrap
the concrete dispatch process and are able to modify that. It is usable for logging, transactions, etc.

There is one builtin interceptor: `TransactionInterceptor`. If you pass it to a `MessageBus`, all message dispatch processes
will be wrapped into a separated transaction.

### AnnotationBasedCommandBus

This kind of message bus uses annotation based configuration, and `TransactionInterceptor` is already registered. `Message` objects
must implement `Command` interface. The typehint in the handler method must be exactly the same as the command object's type.

### AnnotationBasedEventBus

This message bus implementation is annotation based, uses the default typehint handling (subclass handling, etc.). Message objects
must implement `Event` interface.

### Mf4phpMessageBus

If you raise events in your domain models, then I bet that you want to dispatch these events if and only if the already started
transaction has finished successfully. It means that the events must be buffered until the commit is executed properly. This feature
is already implemented in another library called mf4php. `Mf4phpMessageBus` wraps a `MessageDispatcher` which can be an instance of
`TransactedMessageDispatcher`. One of the available implementations of it is `TransactedMemoryMessageDispatcher`, which is a synchronized
solution. Unfortunately, PHP does not support threads so it's not easy to achieve asynchronous processing. However, there is
a Beanstalk based mf4php implementation which supports both transaction based and asynchronous event processing.

If you want to delay some events, you can register `ObjectMessageFactory` objects
which can create the appropriate `DelayableMessage` instances for the defined message class. This feature is highly depending
on the current mf4php implementation.

### Recommended CQRS usage

Let's take the following User aggregate:

```php
AggregateRoot::setEventBus($domainEventBus);

class User extends AggregateRoot
{
    private $id;
    private $email;

    public function modifyEmailAddress($email)
    {
        Assert::email($email);
        $this->raise(new UserEmailModified($this->id, $email));
    }

    /**
     * @Subscribe
     */
    private function handleEmailModification(UserEmailModified $event)
    {
        $this->email = $event->getEmail();
    }
}
```

Predaddy gives an `AggregateRoot` class which provides some opportunities. You should validate incoming parameters in
public methods and if everything is fine, fire a domain event. `AggregateRoot` passes this event immediately to the handler method
inside the aggregate root (which visibility must be protected or private). After that, this event will be passed to the $domainEventBus,
which is probably synchronized to transactions. Fetching the existing user object from a persistent storage and calling the modifyEmailAddress method
are a command handler's responsibilities. After the command bus has committed the transaction, the event will be dispatched to the proper
event handlers registered in $domainEventBus. These handlers might send another commands to achieve eventually consistency.

Paginator components
--------------------

It is a common thing to use paginated results on the user interface. The `presentation` package provides you some useful
classes and interfaces. You can integrate them into your MVC framework to be able to feed them with data coming from the
request. This `Pageable` object can be used in your repository or in any other data provider object as a parameter which can return a `Page` object.
A short example:

```php
$page = 2;
$size = 10;
$sort = Sort::create('name', Direction::$DESC);
$request = new PageRequest($page, $size, $sort);

/* @var $page Page */
$page = $userFinder->getPage($request);
```

In the above example the `$page` object stores the users and a lot of information to be able to create a paginator on the UI.

History
-------

### 1.2

#### Event handling has been refactored

Classes have been moved to a different namespace, you have to modify `use` your statements.

#### Interceptor support added

Take a look at [TransactionInterceptor](https://github.com/szjani/predaddy/blob/1.2/src/predaddy/messagehandling/interceptors/TransactionInterceptor.php).

#### No more marker interface for handler classes

Handler classes do not have to implement any interfaces. You have to remove `implements EventHandler` parts from your classes.

#### MessageCallback added

You can register a callback class for a message post to be able to catch the result.

### 1.1

#### Allowed private variables in events

Until now you had to override `serialize()` and `unserialize()` methods if you added any private members to your event classes even if you extended `EventBase`.
Serialization now uses reflection which might have small performance drawback but it is quite handy. So if you extend EventBase, you can forget this problem.

#### Closures are supported

Useful in unit tests or in simple cases.

```php
$closure = function (Event $event) {
    echo 'will be called with all events';
};
$bus->registerClosure($closure);
$bus->post(new SimpleEvent());