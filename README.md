# FSM Generator

[![Quality Checks](https://github.com/jb-reynaud/fsm-generator/actions/workflows/quality.yml/badge.svg)](https://github.com/jb-reynaud/fsm-generator/actions/workflows/quality.yml)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%20|%208.4-blue)](https://www.php.net/)

A type-safe PHP library for creating Finite State Machines.

## Installation

### Prerequisites

- [PHP 8.1 or 8.4](https://www.php.net/manual/en/install.php)
- [Composer](https://getcomposer.org/)

### Installation

```shell
make install
```

To see all available commands, run:
```shell
make help
```

## How to use

An implementation example of modulo 3 FSM can be found in the `examples` directory. You can run it with:
```shell
make example
```

## Tests

Tests are run with [PHPUnit](https://phpunit.de/). You can run it with:
```shell
make test
```

Quality checks are run with [PHPStan](https://github.com/phpstan/phpstan) & [PhpCsFixer](https://github.com/PHP-CS-Fixer/PHP-CS-Fixer)

## Architecture Decision

### Validation Transition Function
The **transitionFunction** is not validated on construction because it can be slow, if states and/or alphabet are big. The validation is made in the process method, after each transition is applied.
If you want to validate the **transitionFunction**, you can use the `validateTransitionFunction` method.
