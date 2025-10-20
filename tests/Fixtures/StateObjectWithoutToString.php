<?php

declare(strict_types=1);

namespace FSM\Tests\Fixtures;

class StateObjectWithoutToString
{
    public function __construct(public readonly string $name)
    {
    }
}
