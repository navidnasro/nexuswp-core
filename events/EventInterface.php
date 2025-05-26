<?php

namespace jetstore\core\events;

interface EventInterface
{
    public function handle(...$args): void;
}