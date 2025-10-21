# FSM Generator

[![Quality Checks](https://github.com/jb-reynaud/fsm-generator/actions/workflows/quality.yml/badge.svg)](https://github.com/jb-reynaud/fsm-generator/actions/workflows/quality.yml)
![Coverage](https://github.com/jb-reynaud/fsm-generator/blob/main/output/coverage.svg)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%20|%208.4-blue)](https://www.php.net/)

A type-safe PHP library for creating Finite State Machines based on formal automata theory.

## Installation

### For End Users

Install via Composer:
(⚠️ The project will need to be put on packagist)

```bash
composer require jbreynaud/fsm-generator
```

### For Development
```bash
make install
```

To see all available commands:
```bash
make help
```

## Usage

### Basic Example

```php
<?php

use FSM\FiniteStateMachine;

$fsm = new FiniteStateMachine(
    states: ['OFF', 'ON'],
    alphabet: ['0', '1'],
    initialState: 'OFF',
    finalStates: ['ON'],
    transitionFunction: fn (string $state, string $symbol): string => match ([$state, $symbol]) {
        ['OFF', '0'] => 'OFF',
        ['OFF', '1'] => 'ON',
        ['ON', '0'] => 'OFF',
        ['ON', '1'] => 'ON',
        default => throw new \Error("Invalid transition"),
    }
);

// Process input
$fsm->processSequence('101');
echo $fsm->getCurrentState(); // Output: ON
```

### Modulo 3 example

A complete implementation example of a modulo 3 FSM can be found in the `examples` directory. You can run it with:
```bash
make example
```

### API Methods

- `processSequence(string $input): mixed` - Process a sequence and return final state
- `process(string $symbol): void` - Process a single symbol
- `reset(): void` - Reset to initial state
- `validateAllTransitions(): void` - Validate all state transitions upfront

## Tests

Tests are run with [PHPUnit](https://phpunit.de/). You can run it with:
```shell
make test
```

Quality checks are run with [PHPStan](https://github.com/phpstan/phpstan) & [PhpCsFixer](https://github.com/PHP-CS-Fixer/PHP-CS-Fixer)

## Architecture Decisions

### Validation Transition Function
The **transitionFunction** is not validated on construction because it can be slow if states and/or alphabet are large. The validation is performed in the `process()` method after each transition is applied.
If you want to validate the entire **transitionFunction** upfront, you can use the `validateAllTransitions()` method.

### State Types
For state types, the library supports `int|string|bool|float|object|array|null` instead of `mixed` to exclude:
- **resource**: Not serializable, comparison with `===` is unreliable
- **callable**: No practical use case as a state
