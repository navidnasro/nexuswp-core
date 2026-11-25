<?php

namespace nexuswp\core;

use LogicException;

/**
 * Runtime configuration shared between the plugin and the core package.
 *
 * The core never reads plugin classes or WordPress constants on its own: the
 * host plugin hydrates this registry once at boot, so the package stays
 * portable and can be installed on its own.
 */
final class Config
{
    private static string $pluginRoot = '';
    private static string $tablePrefix = '';
    private static string $storagePath = '';
    private static string $publicPath = '';
    private static string $textDomain = 'default';
    private static string $authKey = '';
    private static ?string $migrationPath = null;
    private static bool $configured = false;

    /**
     * @param array{
     *     pluginRoot?: string,
     *     tablePrefix?: string,
     *     storagePath?: string,
     *     publicPath?: string,
     *     textDomain?: string,
     *     authKey?: string,
     *     migrationPath?: string|null
     * } $options
     */
    public static function init(array $options): void
    {
        self::$pluginRoot = rtrim((string)($options['pluginRoot'] ?? ''),'/\\');
        self::$tablePrefix = (string)($options['tablePrefix'] ?? '');
        self::$storagePath = rtrim((string)($options['storagePath'] ?? ''),'/\\');
        self::$publicPath = rtrim((string)($options['publicPath'] ?? ''),'/\\');
        self::$textDomain = (string)($options['textDomain'] ?? 'default');
        self::$authKey = (string)($options['authKey'] ?? (defined('AUTH_KEY') ? AUTH_KEY : ''));
        self::$migrationPath = isset($options['migrationPath']) ? (string)$options['migrationPath'] : null;
        self::$configured = true;
    }

    public static function isConfigured(): bool
    {
        return self::$configured;
    }

    public static function pluginRoot(): string
    {
        return self::$pluginRoot !== '' ? self::$pluginRoot : self::fail('pluginRoot');
    }

    public static function tablePrefix(): string
    {
        return self::$tablePrefix;
    }

    public static function storagePath(): string
    {
        return self::$storagePath !== '' ? self::$storagePath : self::fail('storagePath');
    }

    public static function publicPath(): string
    {
        return self::$publicPath;
    }

    public static function textDomain(): string
    {
        return self::$textDomain;
    }

    public static function authKey(): string
    {
        return self::$authKey;
    }

    /**
     * defaults to <pluginRoot>/database/migrations when not set explicitly
     */
    public static function migrationPath(): string
    {
        return self::$migrationPath ?? self::pluginRoot().'/database/migrations';
    }

    private static function fail(string $key): never
    {
        throw new LogicException("nexuswp\\core\\Config::{$key}() is not set. Call Config::init() during plugin boot.");
    }
}
