<?php

namespace jetstore\core\hooks;

class HookManager
{
    public static function add(HookInterface $hook): void
    {
        $hook->register();
    }
}