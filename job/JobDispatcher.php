<?php

namespace jetstore\core\job;

use InvalidArgumentException;

class JobDispatcher
{
    public static function dispatch(string|JobInterface $job, ...$args): void
    {
        if (is_string($job) && class_exists($job))
            $job = new $job($args);

        if ($job instanceof AbstractJob)
        {
            if ($job->run)
                $job->dispatch();
        }

        else
            throw new InvalidArgumentException("Invalid job passed to dispatcher.");
    }
}
