<?php

namespace jetstore\core\job;

use InvalidArgumentException;
use RuntimeException;

class JobScheduler
{
    private array $jobs = [];

    public function registerJob(string $jobClass, array $args = []): void
    {
        if (!class_exists($jobClass))
            throw new InvalidArgumentException("Job class {$jobClass} not found.");

        $jobInstance = new $jobClass($args);

        if (!$jobInstance instanceof AbstractJob)
            throw new InvalidArgumentException("Job class must extend AbstractJob.");

        $this->jobs[$jobInstance->hook] = $jobInstance;

        if ($jobInstance->run)
            $jobInstance->register();
    }

    public function registerJobs(array $jobClasses): void
    {
        foreach ($jobClasses as $jobClass => $args)
            $this->registerJob($jobClass, $args);
    }

    public function unscheduleAll(): void
    {
        foreach ($this->jobs as $job)
            $job->unschedule();
    }

    public function getJobs(): array
    {
        return $this->jobs;
    }

    public function runJobNow(string $hook, ...$args): void
    {
        if (isset($this->jobs[$hook]))
            $this->jobs[$hook]->handle(...$args);

        else
            throw new RuntimeException("Job with hook '{$hook}' not found.");
    }
}
