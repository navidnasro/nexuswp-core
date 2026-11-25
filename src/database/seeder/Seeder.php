<?php

namespace nexuswp\core\database\seeder;

use Faker\Factory;
use Faker\Generator;
use nexuswp\core\Config;
use RuntimeException;
use wpdb;

abstract class Seeder
{
    protected wpdb $wpdb;
    protected string $prefix;
    protected static ?Generator $faker = null;

    public function __construct()
    {
        global $wpdb;

        $this->wpdb = $wpdb;
        $this->prefix = $wpdb->prefix.Config::tablePrefix();

        if (!class_exists(Factory::class))
            throw new RuntimeException('Faker is required to run seeders. Install it with: composer require --dev fakerphp/faker');

        if (is_null(self::$faker))
            self::$faker = Factory::create('fa_IR');
    }

    abstract public function run(): void;
}
