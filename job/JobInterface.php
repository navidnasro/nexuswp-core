<?php

namespace jetstore\core\job;

interface JobInterface
{
    public function handle(...$args): void;
}