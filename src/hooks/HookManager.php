<?php

namespace nexuswp\core\hooks;

class HookManager
{
    /**
     * Registers an action. Pass a class-string to defer construction until the
     * hook fires; the class is not autoloaded when this line runs.
     */
    public static function action(string $hook, string|Action $handler, int $priority = 10, int $acceptedArgs = 1): void
    {
        add_action($hook, self::callback($handler), $priority, $acceptedArgs);
    }

    /**
     * Registers a filter. Pass a class-string to defer construction.
     */
    public static function filter(string $hook, string|Filter $handler, int $priority = 10, int $acceptedArgs = 1): void
    {
        add_filter($hook, self::callback($handler), $priority, $acceptedArgs);
    }

    private static function callback(string|HookInterface $handler): callable
    {
        if (is_string($handler))
        {
            $instance = null;

            return static function (mixed ...$args) use ($handler, &$instance): mixed
            {
                $instance ??= new $handler();
                return $instance->handle(...$args);
            };
        }

        return [$handler,'handle'];
    }
}
