<?php

namespace nexuswp\core\events;

interface EventInterface
{
    public function handle(...$args): void;
}