<?php

namespace nexuswp\core\route;

use InvalidArgumentException;

/**
 * Rewrite routing.
 *
 * Rules and endpoints are collected in memory and dispatched through a single
 * set of WordPress hooks. Registering one hook pair per rule would add three
 * closures to every request each time a route is declared, so the hook count
 * stays constant no matter how many routes the application has.
 */
class Web
{
    /** @var array<string, array{pattern: string, position: string, handler: array}> */
    private static array $rules = [];

    /** @var array<string, array{places: int, handler: array}> */
    private static array $endpoints = [];

    private static bool $hooksRegistered = false;

    /**
     * handlers may be passed as [ClassName::class, 'method'] so the class is only
     * autoloaded when the endpoint is actually requested.
     */
    public static function resolveHandler(array $handler): array
    {
        if (count($handler) !== 2)
            throw new InvalidArgumentException('A route handler must be [class|object, method].');

        [$target,$method] = $handler;

        if (is_string($target))
        {
            if (!class_exists($target))
                throw new InvalidArgumentException("Route handler class not found: {$target}");

            $target = new $target();
        }

        if (!is_callable([$target,$method]))
            throw new InvalidArgumentException('Route handler is not callable.');

        return [$target,$method];
    }

    public static function registerEndpoint(string $name,int $places,array $handler): void
    {
        self::$endpoints[$name] = ['places' => $places, 'handler' => $handler];
        self::registerHooks();
    }

    public static function registerRule(string $name, array $handler, string $position = 'top'): void
    {
        self::$rules[$name] = [
            'pattern'  => '^'.preg_quote($name,'/').'(?:/.*)?$',
            'position' => $position,
            'handler'  => $handler,
        ];

        self::registerHooks();
    }

    private static function registerHooks(): void
    {
        if (self::$hooksRegistered)
            return;

        self::$hooksRegistered = true;

        add_action('init', [self::class,'addRewriteRules']);

        add_filter('query_vars', static function (array $vars): array
        {
            foreach (array_keys(self::$rules) as $name)
                $vars[] = $name;

            return $vars;
        });

        add_filter('request', static function (array $vars): array
        {
            foreach (array_keys(self::$endpoints) as $name)
                if (isset($vars[$name]))
                    $vars[$name] = true;

            return $vars;
        });

        add_action('template_redirect', [self::class,'dispatch']);
    }

    /**
     * declared on init, when WordPress is ready to accept rewrite rules
     */
    public static function addRewriteRules(): void
    {
        foreach (self::$endpoints as $name => $endpoint)
            add_rewrite_endpoint($name,$endpoint['places']);

        foreach (self::$rules as $name => $rule)
            add_rewrite_rule($rule['pattern'],"index.php?$name=1",$rule['position']);
    }

    /**
     * WordPress callback: renders and terminates when a route matched.
     */
    public static function dispatch(): void
    {
        $output = self::handleRequest();

        if ($output === null)
            return;

        echo $output;
        exit;
    }

    /**
     * one lookup for every declared route; resolves the handler only after a
     * rule matched, so unmatched requests never load a controller
     */
    public static function handleRequest(): ?string
    {
        foreach (self::$endpoints as $name => $endpoint)
        {
            if (get_query_var($name))
                return self::render($endpoint['handler']);
        }

        foreach (self::$rules as $name => $rule)
        {
            if (get_query_var($name))
                return self::render($rule['handler']);
        }

        return null;
    }

    private static function render(array $handler): ?string
    {
        $output = call_user_func(self::resolveHandler($handler));

        return is_string($output) ? $output : null;
    }
}
