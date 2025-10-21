<?php

declare(strict_types=1);

namespace FSM\Tests;

use FSM\Exceptions\InvalidConfigurationException;
use FSM\Exceptions\InvalidStateException;
use FSM\Exceptions\InvalidSymbolException;
use FSM\Exceptions\MissingTransitionException;
use FSM\FiniteStateMachine;
use FSM\Tests\Fixtures\StateEnum;
use FSM\Tests\Fixtures\StateObject;
use FSM\Tests\Fixtures\StateObjectWithoutToString;
use PHPUnit\Framework\TestCase;

class FiniteStateMachineTest extends TestCase
{
    /**
     * @throws InvalidConfigurationException
     */
    private function createModThreeFSM(): FiniteStateMachine
    {
        return new FiniteStateMachine(
            states: ['S0', 'S1', 'S2'],
            alphabet: ['0', '1'],
            initialState: 'S0',
            finalStates: ['S0', 'S1', 'S2'],
            transitionFunction: fn (string $state, string $symbol): string => match ([$state, $symbol]) {
                ['S0', '0'] => 'S0',
                ['S0', '1'] => 'S1',
                ['S1', '0'] => 'S2',
                ['S1', '1'] => 'S0',
                ['S2', '0'] => 'S1',
                ['S2', '1'] => 'S2',
                default => throw new \InvalidArgumentException("Invalid transition: ({$state}, {$symbol})"),
            }
        );
    }

    /**
     * Create an incomplete FSM (missing transition for S1+1)
     *
     * @throws InvalidConfigurationException
     */
    private function createIncompleteFSM(): FiniteStateMachine
    {
        return new FiniteStateMachine(
            states: ['S0', 'S1'],
            alphabet: ['0', '1'],
            initialState: 'S0',
            finalStates: ['S0'],
            transitionFunction: fn (string $state, string $symbol): string => match ([$state, $symbol]) {
                ['S0', '0'] => 'S1',
                ['S0', '1'] => 'S0',
                ['S1', '0'] => 'S0',
                // Missing: ['S1', '1'] - will throw Error
                default => throw new \Error("Unhandled transition: ({$state}, {$symbol})"),
            }
        );
    }

    public function testInitialState(): void
    {
        $fsm = $this->createModThreeFSM();
        $this->assertEquals('S0', $fsm->getCurrentState());
    }

    public function testReset(): void
    {
        $fsm = $this->createModThreeFSM();
        $fsm->process('1');
        $this->assertEquals('S1', $fsm->getCurrentState());

        $fsm->reset();
        $this->assertEquals('S0', $fsm->getCurrentState());
    }

    public function testSingleTransition(): void
    {
        $fsm = $this->createModThreeFSM();

        // S0 + '0' -> S0
        $fsm->process('0');
        $this->assertEquals('S0', $fsm->getCurrentState());

        $fsm->reset();

        // S0 + '1' -> S1
        $fsm->process('1');
        $this->assertEquals('S1', $fsm->getCurrentState());
    }

    /**
     * @dataProvider processedSequenceDataProvider
     */
    public function testProcessSequence(string $binaryInput, string $expectedState): void
    {
        $fsm = $this->createModThreeFSM();
        $output = $fsm->processSequence($binaryInput);
        $this->assertEquals($expectedState, $output);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function processedSequenceDataProvider(): array
    {
        return [
            'no transition (empty string input)' => ['', 'S0'],
            'binary 0 (0 % 3 = 0)' => ['0', 'S0'],
            'binary 100 (4 % 3 = 1)' => ['100', 'S1'],
            'binary 1001 (9 % 3 = 0)' => ['1001', 'S0'],
        ];
    }

    public function testGetters(): void
    {
        $fsm = $this->createModThreeFSM();

        $this->assertEquals(['S0', 'S1', 'S2'], $fsm->getStates());
        $this->assertEquals(['0', '1'], $fsm->getAlphabet());
        $this->assertEquals('S0', $fsm->getInitialState());
        $this->assertEquals(['S0', 'S1', 'S2'], $fsm->getFinalStates());
    }

    public function testInvalidSymbolThrowsException(): void
    {
        $fsm = $this->createModThreeFSM();

        $this->expectException(InvalidSymbolException::class);
        $fsm->process('2'); // '2' is not in the alphabet {0, 1}
    }

    /**
     * @param array<int, int|string|bool|float|object|array<array-key, mixed>|null> $states
     * @param array<int, string> $alphabet
     * @param int|string|bool|float|object|array<array-key, mixed>|null $initialState
     * @param array<int, int|string|bool|float|object|array<array-key, mixed>|null> $finalStates
     *
     * @dataProvider invalidConfigurationDataProvider
     */
    public function testInvalidConfigurationThrowsException(
        array $states,
        array $alphabet,
        int|string|bool|float|object|array|null $initialState,
        array $finalStates,
        string $expectedMessage
    ): void {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($expectedMessage);

        new FiniteStateMachine(
            states: $states,
            alphabet: $alphabet,
            initialState: $initialState,
            finalStates: $finalStates,
            transitionFunction: fn ($s, $sym) => $s
        );
    }

    /**
     * @return array<string, array{array<int, int|string|bool|float|object|array<array-key, mixed>|null>, array<string>, int|string|bool|float|object|array<array-key, mixed>|null, array<int, int|string|bool|float|object|array<array-key, mixed>|null>, string}>
     */
    public static function invalidConfigurationDataProvider(): array
    {
        return [
            'empty states' => [
                [],
                ['0', '1'],
                'S0',
                ['S0'],
                'States set cannot be empty',
            ],
            'empty alphabet' => [
                ['S0'],
                [],
                'S0',
                ['S0'],
                'Alphabet cannot be empty',
            ],
            'alphabet with empty string' => [
                ['S0'],
                ['0', '', '2'],
                'S0',
                ['S0'],
                'All symbols in alphabet must be non-empty strings',
            ],
            'alphabet with duplicate symbols' => [
                ['S0'],
                ['0', '1', '0'],
                'S0',
                ['S0'],
                'All symbols in alphabet must be unique',
            ],
            'initial state not in states set' => [
                ['S0', 'S1'],
                ['0', '1'],
                'S2',
                ['S0'],
                'Initial state must be in the states set',
            ],
            'final state not in states set' => [
                ['S0', 'S1'],
                ['0', '1'],
                'S0',
                ['S0', 'S2'],
                'All final states must be in the states set',
            ],
        ];
    }

    /**
     * @throws InvalidConfigurationException
     * @throws InvalidSymbolException
     */
    public function testTransitionToInvalidStateThrowsException(): void
    {
        $fsm = new FiniteStateMachine(
            states: ['S0', 'S1'],
            alphabet: ['0', '1'],
            initialState: 'S0',
            finalStates: ['S0'],
            transitionFunction: fn ($s, $sym) => 'S2' // Returns invalid state
        );

        $this->expectException(InvalidStateException::class);
        $fsm->process('0');
    }

    public function testMultipleProcessCalls(): void
    {
        $fsm = $this->createModThreeFSM();

        $fsm->reset();
        $fsm->process('1');
        $fsm->process('0');
        $this->assertEquals('S2', $fsm->getCurrentState());
    }

    public function testValidateAllTransitionsSuccess(): void
    {
        $fsm = $this->createModThreeFSM();

        // Should not throw any exception
        $fsm->validateAllTransitions();

        // Verify the current state is unchanged
        $this->assertEquals('S0', $fsm->getCurrentState());
    }

    public function testValidateAllTransitionsThrowsForInvalidState(): void
    {
        $fsm = new FiniteStateMachine(
            states: ['S0', 'S1'],
            alphabet: ['0', '1'],
            initialState: 'S0',
            finalStates: ['S0'],
            transitionFunction: fn (string $state, string $symbol): string => match ([$state, $symbol]) {
                ['S0', '0'] => 'S1',
                ['S0', '1'] => 'S0',
                ['S1', '0'] => 'S0',
                ['S1', '1'] => 'S999', // Invalid state
                default => throw new \Error("Unhandled transition: ({$state}, {$symbol})"),
            }
        );

        $this->expectException(InvalidStateException::class);
        $this->expectExceptionMessage('returns invalid state "S999"');
        $fsm->validateAllTransitions();
    }

    public function testValidateAllTransitionsThrowsForMissingTransition(): void
    {
        $fsm = $this->createIncompleteFSM();

        $this->expectException(MissingTransitionException::class);
        $this->expectExceptionMessage('Transition function is not defined for state "S1" with symbol "1"');
        $fsm->validateAllTransitions();
    }

    public function testProcessThrowsMissingTransitionException(): void
    {
        $fsm = $this->createIncompleteFSM();

        $fsm->process('0'); // Move to S1
        $this->expectException(MissingTransitionException::class);
        $this->expectExceptionMessage('Transition error from state "S1" with symbol "1"');
        $fsm->process('1'); // Should throw MissingTransitionException
    }

    public function testProcessSequenceRestoresStateOnException(): void
    {
        $fsm = $this->createIncompleteFSM();

        $this->assertEquals('S0', $fsm->getCurrentState());

        try {
            $fsm->processSequence('01'); // '0' moves to S1, '1' should fail
            $this->fail('Expected MissingTransitionException to be thrown');
        } catch (MissingTransitionException $e) {
            // State should be restored to initial state
            $this->assertEquals('S0', $fsm->getCurrentState());
        }
    }

    /**
     * @dataProvider mixedStateTypesDataProvider
     * @param array<int, int|string|bool|float|object|array<array-key, mixed>|null> $states
     * @param int|string|bool|float|object|array<array-key, mixed>|null $initialState
     * @param array<int, int|string|bool|float|object|array<array-key, mixed>|null> $finalStates
     * @param int|string|bool|float|object|array<array-key, mixed>|null $expectedFinalState
     */
    public function testMixedStateTypes(
        array $states,
        int|string|bool|float|object|array|null $initialState,
        array $finalStates,
        callable $transitionFunction,
        string $input,
        int|string|bool|float|object|array|null $expectedFinalState
    ): void {
        $fsm = new FiniteStateMachine(
            states: $states,
            alphabet: ['0', '1'],
            initialState: $initialState,
            finalStates: $finalStates,
            transitionFunction: $transitionFunction
        );

        $result = $fsm->processSequence($input);
        $this->assertEquals($expectedFinalState, $result);
    }

    /**
     * @return array<string, array{array<int, int|string|bool|float|object|array<array-key, mixed>|null>, int|string|bool|float|object|array<array-key, mixed>|null, array<int, int|string|bool|float|object|array<array-key, mixed>|null>, callable, string, int|string|bool|float|object|array<array-key, mixed>|null}>
     */
    public static function mixedStateTypesDataProvider(): array
    {
        $objA = new StateObject('A');
        $objB = new StateObject('B');
        $objC = new StateObject('C');

        return [
            'integer states' => [
                [0, 1, 2],
                0,
                [0, 1, 2],
                fn (int $state, string $symbol): int => match ([$state, $symbol]) {
                    [0, '0'] => 0,
                    [0, '1'] => 1,
                    [1, '0'] => 2,
                    [1, '1'] => 0,
                    [2, '0'] => 1,
                    [2, '1'] => 2,
                    default => throw new \Error("Invalid transition"),
                },
                '101',
                2,
            ],
            'boolean states' => [
                [true, false],
                true,
                [true],
                fn (bool $state, string $symbol): bool => match ([$state, $symbol]) {
                    [true, '0'] => true,
                    [true, '1'] => false,
                    [false, '0'] => false,
                    [false, '1'] => true,
                    default => throw new \Error("Invalid transition"),
                },
                '11',
                true,
            ],
            'float states' => [
                [0.0, 1.5, 2.7],
                0.0,
                [0.0],
                fn (float $state, string $symbol): float => match ([$state, $symbol]) {
                    [0.0, '0'] => 0.0,
                    [0.0, '1'] => 1.5,
                    [1.5, '0'] => 2.7,
                    [1.5, '1'] => 0.0,
                    [2.7, '0'] => 1.5,
                    [2.7, '1'] => 2.7,
                    default => throw new \Error("Invalid transition"),
                },
                '1',
                1.5,
            ],
            'array states' => [
                [['state' => 'A'], ['state' => 'B']],
                ['state' => 'A'],
                [['state' => 'A']],
                fn (array $state, string $symbol): array => match ([$state['state'], $symbol]) {
                    ['A', '0'] => ['state' => 'A'],
                    ['A', '1'] => ['state' => 'B'],
                    ['B', '0'] => ['state' => 'A'],
                    ['B', '1'] => ['state' => 'B'],
                    default => throw new \Error("Invalid transition"),
                },
                '1',
                ['state' => 'B'],
            ],
            'object states' => [
                [$objA, $objB, $objC],
                $objA,
                [$objA, $objC],
                fn (StateObject $state, string $symbol): StateObject => match ([$state->name, $symbol]) {
                    ['A', '0'] => $objA,
                    ['A', '1'] => $objB,
                    ['B', '0'] => $objC,
                    ['B', '1'] => $objA,
                    ['C', '0'] => $objB,
                    ['C', '1'] => $objC,
                    default => throw new \Error("Invalid transition"),
                },
                '10',
                $objC,
            ],
            'enum states' => [
                [StateEnum::IDLE, StateEnum::RUNNING, StateEnum::STOPPED],
                StateEnum::IDLE,
                [StateEnum::IDLE, StateEnum::STOPPED],
                fn (StateEnum $state, string $symbol): StateEnum => match ([$state, $symbol]) {
                    [StateEnum::IDLE, '0'] => StateEnum::IDLE,
                    [StateEnum::IDLE, '1'] => StateEnum::RUNNING,
                    [StateEnum::RUNNING, '0'] => StateEnum::STOPPED,
                    [StateEnum::RUNNING, '1'] => StateEnum::IDLE,
                    [StateEnum::STOPPED, '0'] => StateEnum::RUNNING,
                    [StateEnum::STOPPED, '1'] => StateEnum::STOPPED,
                    default => throw new \Error("Invalid transition"),
                },
                '10',
                StateEnum::STOPPED,
            ],
            'mixed types' => [
                [0, 'ONE', 2, null],
                0,
                [0, 2],
                fn (int|string|null $state, string $symbol): int|string|null => match ([$state, $symbol]) {
                    [0, '0'] => 0,
                    [0, '1'] => 'ONE',
                    ['ONE', '0'] => 2,
                    ['ONE', '1'] => 0,
                    [2, '0'] => 'ONE',
                    [2, '1'] => null,
                    [null, '0'] => 0,
                    [null, '1'] => null,
                    default => throw new \Error("Invalid transition"),
                },
                '101',
                null,
            ],
        ];
    }

    /**
     * @dataProvider mixedStateTypesValidationDataProvider
     * @param array<int, int|string|bool|float|object|array<array-key, mixed>|null> $states
     * @param int|string|bool|float|object|array<array-key, mixed>|null $initialState
     */
    public function testValidateAllTransitionsWithMixedTypes(
        array $states,
        int|string|bool|float|object|array|null $initialState,
        callable $transitionFunction
    ): void {
        $fsm = new FiniteStateMachine(
            states: $states,
            alphabet: ['0', '1'],
            initialState: $initialState,
            finalStates: $states,
            transitionFunction: $transitionFunction
        );

        // Should not throw any exception
        $fsm->validateAllTransitions();

        // Verify the current state is unchanged
        $this->assertEquals($initialState, $fsm->getCurrentState());
    }

    /**
     * @return array<string, array{array<int, int|string|bool|float|object|array<array-key, mixed>|null>, int|string|bool|float|object|array<array-key, mixed>|null, callable}>
     */
    public static function mixedStateTypesValidationDataProvider(): array
    {
        $objA = new StateObject('A');
        $objB = new StateObject('B');

        return [
            'integer states validation' => [
                [0, 1, 2],
                0,
                fn (int $state, string $symbol): int => match ([$state, $symbol]) {
                    [0, '0'] => 0,
                    [0, '1'] => 1,
                    [1, '0'] => 2,
                    [1, '1'] => 0,
                    [2, '0'] => 1,
                    [2, '1'] => 2,
                    default => throw new \Error("Invalid transition"),
                },
            ],
            'boolean states validation' => [
                [true, false],
                true,
                fn (bool $state, string $symbol): bool => match ([$state, $symbol]) {
                    [true, '0'] => true,
                    [true, '1'] => false,
                    [false, '0'] => false,
                    [false, '1'] => true,
                    default => throw new \Error("Invalid transition"),
                },
            ],
            'object states validation' => [
                [$objA, $objB],
                $objA,
                fn (StateObject $state, string $symbol): StateObject => match ([$state->name, $symbol]) {
                    ['A', '0'] => $objA,
                    ['A', '1'] => $objB,
                    ['B', '0'] => $objA,
                    ['B', '1'] => $objB,
                    default => throw new \Error("Invalid transition"),
                },
            ],
            'enum states validation' => [
                [StateEnum::IDLE, StateEnum::RUNNING, StateEnum::STOPPED],
                StateEnum::IDLE,
                fn (StateEnum $state, string $symbol): StateEnum => match ([$state, $symbol]) {
                    [StateEnum::IDLE, '0'] => StateEnum::IDLE,
                    [StateEnum::IDLE, '1'] => StateEnum::RUNNING,
                    [StateEnum::RUNNING, '0'] => StateEnum::STOPPED,
                    [StateEnum::RUNNING, '1'] => StateEnum::IDLE,
                    [StateEnum::STOPPED, '0'] => StateEnum::RUNNING,
                    [StateEnum::STOPPED, '1'] => StateEnum::STOPPED,
                    default => throw new \Error("Invalid transition"),
                },
            ],
            'mixed types validation' => [
                [0, 'ONE', 2],
                0,
                fn (int|string $state, string $symbol): int|string => match ([$state, $symbol]) {
                    [0, '0'] => 0,
                    [0, '1'] => 'ONE',
                    ['ONE', '0'] => 2,
                    ['ONE', '1'] => 0,
                    [2, '0'] => 'ONE',
                    [2, '1'] => 2,
                    default => throw new \Error("Invalid transition"),
                },
            ],
        ];
    }

    /**
     * Test FSM with multi-character symbols
     *
     * @throws InvalidConfigurationException
     * @throws InvalidSymbolException
     * @throws InvalidStateException
     * @throws MissingTransitionException
     */
    public function testMultiCharacterSymbols(): void
    {
        $fsm = new FiniteStateMachine(
            states: ['S0', 'S1', 'S2'],
            alphabet: ['hello', 'world', ' '],
            initialState: 'S0',
            finalStates: ['S2'],
            transitionFunction: fn (string $state, string $symbol): string => match ([$state, $symbol]) {
                ['S0', 'hello'] => 'S1',
                ['S1', ' '] => 'S1',
                ['S1', 'world'] => 'S2',
                default => throw new \Error("Invalid transition"),
            }
        );

        $result = $fsm->processSequence('hello world');
        $this->assertEquals('S2', $result);
    }

    /**
     * Test greedy tokenization with overlapping prefixes
     *
     * @throws InvalidConfigurationException
     * @throws InvalidSymbolException
     * @throws InvalidStateException
     * @throws MissingTransitionException
     */
    public function testGreedyTokenizationWithPrefixes(): void
    {
        $fsm = new FiniteStateMachine(
            states: ['S0', 'S1', 'S2', 'S3'],
            alphabet: ['a', 'aa', 'aab'],
            initialState: 'S0',
            finalStates: ['S0', 'S1', 'S2', 'S3'],
            transitionFunction: fn (string $state, string $symbol): string => match ([$state, $symbol]) {
                ['S0', 'aab'] => 'S1',
                ['S1', 'aa'] => 'S2',
                ['S2', 'a'] => 'S3',
                ['S0', 'a'] => 'S0',
                ['S0', 'aa'] => 'S0',
                default => throw new \Error("Invalid transition"),
            }
        );

        // 'aab' should match 'aab' (greedy), not 'aa' + 'b' or 'a' + 'a' + 'b'
        // 'aaa' should match 'aa' + 'a' (greedy)
        $result = $fsm->processSequence('aabaaa');
        $this->assertEquals('S3', $result);
    }

    /**
     * Test tokenization error when no symbol matches
     *
     * @throws InvalidConfigurationException
     */
    public function testTokenizationErrorNoMatch(): void
    {
        $fsm = new FiniteStateMachine(
            states: ['S0', 'S1'],
            alphabet: ['hello', 'world'],
            initialState: 'S0',
            finalStates: ['S1'],
            transitionFunction: fn (string $state, string $symbol): string => match ([$state, $symbol]) {
                ['S0', 'hello'] => 'S1',
                ['S1', 'world'] => 'S0',
                default => throw new \Error("Invalid transition"),
            }
        );

        $this->expectException(InvalidSymbolException::class);
        $this->expectExceptionMessageMatches('/Cannot tokenize input at position/');
        $fsm->processSequence('helloXworld');
    }

    /**
     * Test validateAllTransitions with multi-character alphabet
     *
     * @throws InvalidConfigurationException
     * @throws InvalidStateException
     * @throws MissingTransitionException
     */
    public function testValidateAllTransitionsWithMultiCharacterAlphabet(): void
    {
        $fsm = new FiniteStateMachine(
            states: ['idle', 'running', 'stopped'],
            alphabet: ['start', 'stop', 'reset'],
            initialState: 'idle',
            finalStates: ['stopped'],
            transitionFunction: fn (string $state, string $symbol): string => match ([$state, $symbol]) {
                ['idle', 'start'] => 'running',
                ['idle', 'stop'] => 'idle',
                ['idle', 'reset'] => 'idle',
                ['running', 'start'] => 'running',
                ['running', 'stop'] => 'stopped',
                ['running', 'reset'] => 'idle',
                ['stopped', 'start'] => 'running',
                ['stopped', 'stop'] => 'stopped',
                ['stopped', 'reset'] => 'idle',
                default => throw new \Error("Invalid transition"),
            }
        );

        $fsm->validateAllTransitions();
        $this->assertEquals('idle', $fsm->getCurrentState());
    }

    /**
     * Test greedy matching prefers longest match
     *
     * @throws InvalidConfigurationException
     * @throws InvalidSymbolException
     * @throws InvalidStateException
     * @throws MissingTransitionException
     */
    public function testGreedyMatchingPrefersLongestMatch(): void
    {
        $callLog = [];

        $fsm = new FiniteStateMachine(
            states: ['S0'],
            alphabet: ['a', 'aa', 'aaa'],
            initialState: 'S0',
            finalStates: ['S0'],
            transitionFunction: function (string $state, string $symbol) use (&$callLog): string {
                $callLog[] = $symbol;
                return 'S0';
            }
        );

        $fsm->processSequence('aaaaaa');

        // Should match 'aaa' + 'aaa' (greedy), not 'aa' + 'aa' + 'aa' or 'a' * 6
        $this->assertEquals(['aaa', 'aaa'], $callLog);
    }

    /**
     * @dataProvider stateToStringDataProvider
     *
     * @param int|string|bool|float|object|array<array-key, mixed>|null $state
     */
    public function testStateToString(int|string|bool|float|object|array|null $state, string $expectedPattern): void
    {
        $result = FiniteStateMachine::stateToString($state);
        $this->assertEquals($expectedPattern, $result);
    }

    /**
     * @return array<string, array{int|string|bool|float|object|array<array-key, mixed>|null, string}>
     */
    public static function stateToStringDataProvider(): array
    {
        $objectWithToString = new StateObject('test-state');
        $objectWithoutToString = new StateObjectWithoutToString('test');

        return [
            'integer state' => [42, '42'],
            'string state' => ['S0', 'S0'],
            'boolean true state' => [true, 'true'],
            'boolean false state' => [false, 'false'],
            'float state' => [3.14, '3.14'],
            'null state' => [null, 'NULL'],
            'array state' => [['key' => 'value'], 'Array'],
            'object with __toString' => [$objectWithToString, 'test-state'],
            'object without __toString' => [$objectWithoutToString, 'FSM\Tests\Fixtures\StateObjectWithoutToString'],
        ];
    }
}
