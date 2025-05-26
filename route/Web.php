<?php

namespace jetstore\core\route;

class Web
{
    public static function registerEndpoint(string $name,int $places,array $handler): void
    {
        // adds the endpoint
        add_action('init',function () use ($name,$places)
        {
            add_rewrite_endpoint($name,$places);
        });

        // adds the endpoint name into query vars
        add_filter('request', function($vars) use ($name)
        {
            if (isset($vars[$name]))
                $vars[$name] = true;

            return $vars;
        });

        // rendering the template for endpoint
        add_action('template_redirect', function() use ($name,$handler)
        {
            if (get_query_var($name))
            {
                echo call_user_func($handler);
                exit;
            }
        });
    }

    public static function registerRule(string $name, array $handler, string $position = 'top'): void
    {
        // adds the endpoint
        add_action('init',function () use ($name, $position)
        {
            add_rewrite_rule("^$name(?:/.*)?$", "index.php?$name=1", $position);
        });

        // adds the endpoint name into query vars
        add_filter('query_vars', function($vars) use ($name)
        {
            $vars[] = $name;

            return $vars;
        });

        // rendering the template for endpoint
        add_action('template_redirect', function() use ($name, $handler)
        {
            if (get_query_var($name))
            {
                echo call_user_func($handler);
                exit;
            }
        });
    }
}