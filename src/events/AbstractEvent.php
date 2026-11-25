<?php

namespace nexuswp\core\events;

abstract class AbstractEvent implements EventInterface
{
    /**
     * static event name, so a listener can be registered without being
     * instantiated. falls back to the instance property when left empty.
     */
    public const NAME = '';

    public string $eventName = '';
    public bool $trigger = true;
}
