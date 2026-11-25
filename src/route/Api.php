<?php

namespace nexuswp\core\route;

use Closure;
use nexuswp\core\Config;
use nexuswp\core\support\Jwt;
use WP_Error;

class Api
{
    private static ?Closure $tokenVerifier = null;

    /**
     * replaces the default bearer token check.
     *
     * the verifier receives the raw token and returns the user id, or null when
     * the token is not acceptable. lets the plugin add rotation, revocation or a
     * different token format without touching the core.
     */
    public static function setTokenVerifier(Closure $verifier): void
    {
        self::$tokenVerifier = $verifier;
    }

    public static function register(string $verb,string $url,array $handler,bool $hasToken = true,string $capability = 'edit_users'): void
    {
        add_action('rest_api_init', function () use ($verb,$url,$handler,$hasToken,$capability)
        {
            $permission = $hasToken
                ? function () use ($capability) { return self::checkBearerToken($capability); }
                : '__return_true';

            register_rest_route(
                'api',
                $url,
                [
                    'methods' => $verb,
                    'callback' => fn($request) => self::invoke($handler,$request),
                    'permission_callback' => $permission,
                ]
            );
        });
    }

    /**
     * resolves [class-string, method] handlers at call time, so a controller is
     * only autoloaded for the request that actually hits its route.
     */
    private static function invoke(array $handler, mixed $request): mixed
    {
        [$target,$method] = $handler;

        if (is_string($target))
            $target = new $target();

        return $target->{$method}($request);
    }

    public static function checkBearerToken(string $capability = 'edit_users'): WP_Error|bool
    {
        $header = self::getAuthorizationHeader();

        if (empty($header) || !preg_match('/^Bearer\s+(\S+)$/i', $header, $matches))
        {
            return new WP_Error(
                'unauthorized',
                'Bearer token not found',
                ['status' => 401]
            );
        }

        $userId = self::$tokenVerifier
            ? (int)(self::$tokenVerifier)($matches[1])
            : self::defaultTokenVerifier($matches[1]);

        if ($userId <= 0)
        {
            return new WP_Error(
                'unauthorized',
                'Invalid or expired token',
                ['status' => 401]
            );
        }

        $user = get_userdata($userId);

        if (!$user)
        {
            return new WP_Error(
                'unauthorized',
                'Invalid or expired token',
                ['status' => 401]
            );
        }

        if (!user_can($user,$capability))
        {
            return new WP_Error(
                'forbidden',
                'You are not allowed to perform this action',
                ['status' => 403]
            );
        }

        return true;
    }

    private static function defaultTokenVerifier(string $token): int
    {
        $payload = Jwt::decode($token,Config::authKey());

        if (!$payload || empty($payload->user_id))
            return 0;

        return (int)$payload->user_id;
    }

    /**
     * reads the Authorization header, including FastCGI setups that hide it
     */
    private static function getAuthorizationHeader(): string
    {
        if (!empty($_SERVER['HTTP_AUTHORIZATION']))
            return $_SERVER['HTTP_AUTHORIZATION'];

        if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION']))
            return $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];

        if (function_exists('getallheaders'))
        {
            foreach (getallheaders() as $name => $value)
                if (strcasecmp($name,'Authorization') === 0)
                    return $value;
        }

        return '';
    }
}
