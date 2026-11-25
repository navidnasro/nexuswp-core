<?php

namespace nexuswp\core\job;

interface JobInterface
{
    public function handle(...$args): void;
}