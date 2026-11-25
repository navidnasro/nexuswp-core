<?php

namespace nexuswp\core\job;

abstract class AbstractJob implements JobInterface
{
    /**
     * option that mirrors jobs which must be re-attached on cron requests only.
     * reading it on every page load used to cost an option lookup on requests
     * that never run a job.
     */
    public const REGISTRY_OPTION = 'nexuswp_jobs_need_explicit_run';

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

        // scheduling is idempotent; JobScheduler calls this during init
        $this->schedule();

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
        $actions = get_option(self::REGISTRY_OPTION);

        $actions = is_array($actions) ? $actions : [];

        $newAction = [
            'hook'      => $this->hook,
            'class'     => static::class,
            'method'    => 'dispatchHook',
            'argsCount' => count($this->args) ?: 1
        ];

        // register() runs on every request, so the same job must not be added twice
        foreach ($actions as $action)
            if ($action['hook'] === $newAction['hook'] && $action['class'] === $newAction['class'])
                return;

        $actions[] = $newAction;

        update_option(self::REGISTRY_OPTION, $actions);
    }

    public function removeStoredAction(array $actionToRemove): void
    {
        $actions = get_option(self::REGISTRY_OPTION);

        if (!$actions)
            return;

        foreach ($actions as $key => $action)
            if ($action === $actionToRemove)
                unset($actions[$key]);

        update_option(self::REGISTRY_OPTION, $actions);
    }

    /**
     * re-attaches stored job hooks, but only on requests where cron can run.
     * returns the number of hooks attached.
     */
    public static function restoreRegisteredActions(): int
    {
        if (!self::isCronRequest())
            return 0;

        $actions = get_option(self::REGISTRY_OPTION);

        if (!is_array($actions) || $actions === [])
            return 0;

        $attached = 0;

        foreach ($actions as $action)
        {
            $hook = $action['hook'] ?? '';
            $class = $action['class'] ?? '';
            $method = $action['method'] ?? 'dispatchHook';
            $argsCount = $action['argsCount'] ?? 1;

            if ($hook === '' || !class_exists($class))
                continue;

            $object = new $class();

            if (!$object instanceof self || !method_exists($object,$method))
                continue;

            add_action($hook, [$object, $method], 10, (int)$argsCount);
            $attached++;
        }

        return $attached;
    }

    private static function isCronRequest(): bool
    {
        if (defined('DOING_CRON') && DOING_CRON)
            return true;

        return function_exists('wp_doing_cron') && wp_doing_cron();
    }
}
