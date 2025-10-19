<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use FSM\Exceptions\InvalidSymbolException;
use FSM\FiniteStateMachine;

echo "\033[32mExample of modulo 3 (FSM).\033[0m\n";

// 1. Construct the FSM.
try {
    $fsm = new FiniteStateMachine(
        states: [0, 1, 2],
        alphabet: ['0', '1'],
        initialState: 0,
        finalStates: [0, 1, 2],
        transitionFunction: fn ($state, $symbol) => match ([$state, $symbol]) {
            [0, '0'] => 0, [0, '1'] => 1,
            [1, '0'] => 2, [1, '1'] => 0,
            [2, '0'] => 1, [2, '1'] => 2,
        }
    );
} catch (\Exception $e) {
    printf("Error: %s", $e->getMessage());
    die();
}

// 2. Test the FSM.
$tests = [
    '0',
    '1001011101',
    '118218lol',
    ''
];

foreach ($tests as $binary) {
    try {
        printf("Binary %10s modulo 3: ", $binary);

        $fsm->reset();
        $output = $fsm->processSequence($binary);

        printf("\033[32m%s\033[0m\n", $output);
    } catch (InvalidSymbolException $e) {
        printf("InvalidSymbolException: %s\n", $e->getMessage());
    }
}
