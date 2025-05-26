<?php

namespace jetstore\core\events;

abstract class AbstractEvent implements EventInterface
{
    public string $eventName = '';
    public bool $trigger = true;
}