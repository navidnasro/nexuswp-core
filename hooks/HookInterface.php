<?php

namespace jetstore\core\hooks;

interface HookInterface
{
    public function register(): void;
    public function handle(): mixed;
}