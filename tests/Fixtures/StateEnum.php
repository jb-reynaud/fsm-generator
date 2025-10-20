<?php

declare(strict_types=1);

namespace FSM\Tests\Fixtures;

enum StateEnum: string
{
    case IDLE = 'idle';
    case RUNNING = 'running';
    case STOPPED = 'stopped';
}
