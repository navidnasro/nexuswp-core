<?php

namespace nexuswp\core\database\seeder;

use nexuswp\core\Config;
use wpdb;

class SeederManager
{
    private wpdb $wpdb;
    protected array $seeders = [];
    private string $seedersTable;

    public function __construct()
    {
        global $wpdb;

        $this->wpdb = $wpdb;
        $this->seedersTable = $wpdb->prefix . Config::tablePrefix() .'seeders';
    }

    public function runSeeders(): void
    {
        foreach (glob(Config::pluginRoot().'/database/seeders/*.php') as $file)
        {
            $seederName = basename($file);
            $seeder = require $file;

            $exists = $this->wpdb->get_var($this->wpdb->prepare("SELECT COUNT(*) FROM {$this->seedersTable} WHERE seeder = %s", $seederName));

            if (!$exists && ($seeder instanceof Seeder))
            {
                $seeder->run();
                $this->wpdb->insert($this->seedersTable, ['seeder' => $seederName, 'created_at' => current_time('mysql')]);
            }
        }
    }
}
