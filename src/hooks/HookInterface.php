<?php

namespace nexuswp\core\hooks;

interface HookInterface
{
    public function handle(mixed ...$args): mixed;
}
