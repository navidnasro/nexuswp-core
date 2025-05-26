<?php

namespace jetstore\core\events;

class EventListener
{
    private static array $listeners = [];

    public static function register(Event $event): void
    {
        $name = $event->eventName;
        self::$listeners[$name][] = $event;
    }

    public static function trigger(string $eventName, ...$args): void
    {
        if (!isset(self::$listeners[$eventName]))
            return;

        foreach (self::$listeners[$eventName] as $listener)
        {
            if ($listener instanceof Event)
            {
                if ($listener->trigger)
                    $listener->handle(...$args);
            }
        }
    }

    public static function triggerLatest(string $eventName, ...$args): void
    {
        if (!isset(self::$listeners[$eventName]))
            return;

        $index = count(self::$listeners[$eventName]) -1;
        $listener = self::$listeners[$eventName][$index];

        if (isset($listener) && $listener instanceof Event)
        {
            if ($listener->trigger)
                $listener->handle(...$args);
        }
    }

    public static function triggerFirst(string $eventName, ...$args): void
    {
        if (!isset(self::$listeners[$eventName]))
            return;

        $listener = self::$listeners[$eventName][0];

        if (isset($listener) && $listener instanceof Event)
        {
            if ($listener->trigger)
                $listener->handle(...$args);
        }
    }

    public static function triggerNListeners(string $eventName, int $n, ...$args): void
    {
        if (!isset(self::$listeners[$eventName]))
            return;

        foreach (self::$listeners[$eventName] as $index => $listener)
        {
            if ($index >= $n)
                break;

            if ($listener instanceof Event)
            {
                if ($listener->trigger)
                    $listener->handle(...$args);
            }
        }
    }

    public static function triggerNthListener(string $eventName, int $n, ...$args): void
    {
        if (!isset(self::$listeners[$eventName]))
            return;

        $listener = self::$listeners[$eventName][$n+1];

        if (isset($listener) && $listener instanceof Event)
        {
            if ($listener->trigger)
                $listener->handle(...$args);
        }
    }
}