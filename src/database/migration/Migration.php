<?php

namespace nexuswp\core\database\migration;

use nexuswp\core\Config;
use wpdb;

abstract class Migration
{
    protected wpdb $wpdb;
    protected string $prefix;
    protected string $charset;

    public function __construct()
    {
        global $wpdb;

        $this->wpdb = $wpdb;
        $this->prefix = $wpdb->prefix.Config::tablePrefix();
        $this->charset = $wpdb->get_charset_collate();
    }

    abstract public function up(): void;
    abstract public function down(): void;
}