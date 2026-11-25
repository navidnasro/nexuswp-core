<?php

namespace nexuswp\core\events;

use InvalidArgumentException;

class EventListener
{
    /** @var array<string, array<int, Event|class-string<Event>>> */
    private static array $listeners = [];

    /**
     * Registers a listener under an explicit event name.
     *
     * The name is passed separately instead of read from the class so a
     * class-string listener is never autoloaded during registration; it is
     * built the first time the event fires and reused afterwards.
     */
    public static function on(string $eventName, string|Event $listener): void
    {
        if ($eventName === '')
            throw new InvalidArgumentException('An event must expose a non-empty name.');

        self::$listeners[$eventName][] = $listener;
    }

    public static function trigger(string $eventName, ...$args): void
    {
        if (!isset(self::$listeners[$eventName]))
            return;

        foreach (array_keys(self::$listeners[$eventName]) as $index)
            self::dispatch($eventName,$index,$args);
    }

    public static function triggerLatest(string $eventName, ...$args): void
    {
        if (!isset(self::$listeners[$eventName]))
            return;

        $index = count(self::$listeners[$eventName]) - 1;

        if (isset(self::$listeners[$eventName][$index]))
            self::dispatch($eventName,$index,$args);
    }

    public static function triggerFirst(string $eventName, ...$args): void
    {
        if (!isset(self::$listeners[$eventName]))
            return;

        if (isset(self::$listeners[$eventName][0]))
            self::dispatch($eventName,0,$args);
    }

    public static function triggerNListeners(string $eventName, int $n, ...$args): void
    {
        if (!isset(self::$listeners[$eventName]))
            return;

        foreach (array_keys(self::$listeners[$eventName]) as $index)
        {
            if ($index >= $n)
                break;

            self::dispatch($eventName,$index,$args);
        }
    }

    public static function triggerNthListener(string $eventName, int $n, ...$args): void
    {
        if (!isset(self::$listeners[$eventName]))
            return;

        if (isset(self::$listeners[$eventName][$n]))
            self::dispatch($eventName,$n,$args);
    }

    private static function dispatch(string $eventName,int $index,array $args): void
    {
        $listener = self::resolve($eventName,$index);

        if ($listener instanceof Event && $listener->trigger)
            $listener->handle(...$args);
    }

    /**
     * swaps a registered class-string for a constructed instance on first use,
     * so later triggers do not pay for construction again.
     */
    private static function resolve(string $eventName,int $index): ?Event
    {
        $listener = self::$listeners[$eventName][$index] ?? null;

        if (is_string($listener))
        {
            if (!is_subclass_of($listener, Event::class))
                throw new InvalidArgumentException("Not an event class: {$listener}");

            $listener = new $listener();
            self::$listeners[$eventName][$index] = $listener;
        }

        return $listener instanceof Event ? $listener : null;
    }

}
