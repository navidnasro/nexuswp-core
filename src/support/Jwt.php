<?php

namespace nexuswp\core\support;

/**
 * Minimal, dependency free HS256 token helper.
 *
 * Tokens are `payload.signature` and must always carry an `exp` claim: the
 * framework deliberately has no non-expiring token path.
 */
final class Jwt
{
    public static function encode(array $payload,string $key): string
    {
        $body = self::base64UrlEncode((string)json_encode($payload));
        $signature = self::base64UrlEncode(hash_hmac('sha256',$body,$key,true));

        return $body.'.'.$signature;
    }

    public static function decode(string $token,string $key): object|false
    {
        $parts = explode('.',$token);

        if (count($parts) !== 2)
            return false;

        [$body,$signature] = $parts;

        if (!hash_equals(self::base64UrlEncode(hash_hmac('sha256',$body,$key,true)),$signature))
            return false;

        $payload = json_decode(self::base64UrlDecode($body));

        if (!is_object($payload) || !isset($payload->exp))
            return false;

        if (!is_numeric($payload->exp) || (int)$payload->exp < time())
            return false;

        return $payload;
    }

    public static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data),'+/','-_'),'=');
    }

    public static function base64UrlDecode(string $data): string
    {
        $padding = strlen($data) % 4;

        if ($padding)
            $data .= str_repeat('=',4 - $padding);

        return (string)base64_decode(strtr($data,'-_','+/'),true);
    }
}
