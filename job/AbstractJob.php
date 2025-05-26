<?php

namespace jetstore\core\job;

abstract class AbstractJob implements JobInterface
{
    public bool $run = true;
    public int $startInSeconds = 0;
    public string $hook = '';
    public bool $globallyCalled = true;
    public array $interval = []; // ['name' => '...', 'interval' => seconds, 'display' => '...']
    public array $args = [];

    public function __construct(array $args = [])
    {
        $this->args = $args;

        if (!$this->run)
            $this->unschedule();
    }

    public function register(): void
    {
        if (!empty($this->interval))
            add_filter('cron_schedules', [$this, 'setInterval']);

        add_action('init', [$this, 'schedule']);

        if ($this->globallyCalled)
            add_action($this->hook, [$this, 'dispatchHook'], 10, count($this->args) ?: 1);
        else
            $this->storeJobAction();
    }

    public function setInterval($schedules): array
    {
        $schedules[$this->interval['name']] = [
            'interval' => $this->interval['interval'],
            'display' => $this->interval['display'],
        ];

        return $schedules;
    }

    public function schedule(): void
    {
        if (!wp_next_scheduled($this->hook, $this->args))
        {
            $intervalName = $this->interval['name'] ?? 'daily';
            wp_schedule_event(time() + $this->startInSeconds, $intervalName, $this->hook, $this->args);
        }
    }

    public function unschedule(): void
    {
        if (wp_next_scheduled($this->hook, $this->args) !== false)
            wp_clear_scheduled_hook($this->hook, $this->args);
    }

    public function dispatch(): void
    {
        if (wp_next_scheduled($this->hook, $this->args) !== false)
            wp_clear_scheduled_hook($this->hook, $this->args);

        wp_schedule_single_event(time() + $this->startInSeconds, $this->hook, $this->args);

        if ($this->globallyCalled)
            add_action($this->hook, [$this, 'dispatchHook'], 10, count($this->args) ?: 1);
        else
            $this->storeJobAction();
    }

    public function dispatchHook(...$args): void
    {
        $this->handle(...$args);
    }

    public function storeJobAction(): void
    {
        $actions = get_option('cronjobs_that_need_explicit_run');

        if (!$actions)
            $actions = [];

        $actions[] = [
            'hook'      => $this->hook,
            'class'     => static::class,
            'method'    => 'dispatchHook',
            'argsCount' => count($this->args) ?: 1
        ];

        update_option('cronjobs_that_need_explicit_run', $actions);
    }

    public function removeStoredAction(array $actionToRemove): void
    {
        $actions = get_option('cronjobs_that_need_explicit_run');

        if (!$actions)
            return;

        foreach ($actions as $key => $action)
            if ($action === $actionToRemove)
                unset($actions[$key]);

        update_option('cronjobs_that_need_explicit_run', $actions);
    }
}
