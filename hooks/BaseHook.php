<?php

namespace jetstore\core\hooks;

abstract class BaseHook implements HookInterface
{
    protected string $hook = '';
    protected int $priority = 10;
    protected int $acceptedArgs = 1;

    public function register(): void
    {
        if ($this instanceof Action)
            add_action($this->hook, [$this, 'handle'], $this->priority, $this->acceptedArgs);

        elseif ($this instanceof Filter)
            add_filter($this->hook, [$this, 'handle'], $this->priority, $this->acceptedArgs);
    }
}