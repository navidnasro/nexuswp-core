<?php

namespace nexuswp\core\job;

use InvalidArgumentException;
use RuntimeException;

class JobScheduler
{
    /** @var array<int, array{class: string, args: array}> */
    private array $pending = [];

    /** @var array<string, AbstractJob> */
    private array $jobs = [];

    public function registerJob(string $jobClass, array $args = []): void
    {
        $index = count($this->pending);
        $this->pending[$index] = ['class' => $jobClass, 'args' => $args];

        // construction is deferred to init, so a request that never runs a job
        // does not pay for loading every registered job class
        add_action('init', function () use ($index): void
        {
            $job = $this->resolve($index);

            if ($job->run)
                $job->register();
        });
    }

    public function registerJobs(array $jobClasses): void
    {
        foreach ($jobClasses as $jobClass => $args)
            $this->registerJob($jobClass, $args);
    }

    public function unscheduleAll(): void
    {
        // jobs that were never resolved still hold their hook in the pending
        // list, so resolve them first instead of skipping them silently
        foreach (array_keys($this->pending) as $index)
            $this->resolve($index);

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

    private function resolve(int $index): AbstractJob
    {
        $entry = $this->pending[$index] ?? null;

        if ($entry === null)
            throw new InvalidArgumentException('Unknown job registration.');

        $jobClass = $entry['class'];

        if (!class_exists($jobClass))
            throw new InvalidArgumentException("Job class {$jobClass} not found.");

        $job = new $jobClass($entry['args']);

        if (!$job instanceof AbstractJob)
            throw new InvalidArgumentException("Job class must extend AbstractJob.");

        unset($this->pending[$index]);
        $this->jobs[$job->hook] = $job;

        return $job;
    }
}
