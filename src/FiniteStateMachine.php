<?php

declare(strict_types=1);

namespace FSM;

use FSM\Exceptions\InvalidConfigurationException;
use FSM\Exceptions\InvalidStateException;
use FSM\Exceptions\InvalidSymbolException;
use FSM\Exceptions\MissingTransitionException;

/**
 * Finite State Machine (FSM) implementation based on formal automata theory.
 *
 * A finite automaton is a 5-tuple (Q, Σ, q0, F, δ) where:
 * - Q is a finite set of states
 * - Σ is a finite input alphabet
 * - q0 ∈ Q is the initial state
 * - F ⊆ Q is the set of accepting/final states
 * - δ: Q × Σ → Q is the transition function
 */
class FiniteStateMachine
{
    /**
     * @var array<int, int|string|bool|float|object|array<array-key, mixed>|null> Set of states (Q)
     */
    private array $states;

    /**
     * @var array<int, string> Input alphabet (Σ)
     */
    private array $alphabet;

    /**
     * @var int|string|bool|float|object|array<array-key, mixed>|null Initial state (q0)
     */
    private int|string|bool|float|object|array|null $initialState;

    /**
     * @var array<int, int|string|bool|float|object|array<array-key, mixed>|null> Set of final/accepting states (F)
     */
    private array $finalStates;

    /**
     * @var callable Transition function δ: Q × Σ → Q
     */
    private $transitionFunction;

    /**
     * @var int|string|bool|float|object|array<array-key, mixed>|null
     */
    private int|string|bool|float|object|array|null $currentState;

    /**
     * Create a new Finite State Machine.
     *
     * @param array<int, int|string|bool|float|object|array<array-key, mixed>|null> $states Set of all possible states
     * @param array<string> $alphabet Set of input symbols
     * @param int|string|bool|float|object|array<array-key, mixed>|null $initialState The initial state
     * @param array<int, int|string|bool|float|object|array<array-key, mixed>|null> $finalStates Set of accepting states
     * @param callable $transitionFunction Function (state, symbol) => nextState
     *
     * @throws InvalidConfigurationException
     */
    public function __construct(
        array $states,
        array $alphabet,
        int|string|bool|float|object|array|null $initialState,
        array $finalStates,
        callable $transitionFunction
    ) {
        $this->states = \array_values($states);
        $this->alphabet = \array_values($alphabet);
        $this->initialState = $initialState;
        $this->finalStates = \array_values($finalStates);
        $this->transitionFunction = $transitionFunction;

        $this->validateConfiguration();

        \usort($this->alphabet, fn (string $a, string $b): int => \mb_strlen($b) <=> \mb_strlen($a));

        $this->reset();
    }

    /**
     * Validate the configuration of the FSM. Rules are:
     * - States should not be empty
     * - Alphabet should not be empty and contain only unique symbols
     * - Initial state should be in the States set
     * - Final states should be in the States set
     *
     * @throws InvalidConfigurationException
     */
    private function validateConfiguration(): void
    {
        if (empty($this->states)) {
            throw new InvalidConfigurationException('States set cannot be empty.');
        }

        if (empty($this->alphabet)) {
            throw new InvalidConfigurationException('Alphabet cannot be empty.');
        }
        if (\in_array('', $this->alphabet, true)) {
            throw new InvalidConfigurationException('All symbols in alphabet must be non-empty strings.');
        }
        if (\count($this->alphabet) !== \count(\array_unique($this->alphabet))) {
            throw new InvalidConfigurationException('All symbols in alphabet must be unique.');
        }

        if (!\in_array($this->initialState, $this->states, true)) {
            throw new InvalidConfigurationException('Initial state must be in the states set.');
        }

        foreach ($this->finalStates as $finalState) {
            if (!\in_array($finalState, $this->states, true)) {
                throw new InvalidConfigurationException('All final states must be in the states set.');
            }
        }
    }


    /**
     * Process a single input symbol and transition to the next state.
     *
     * @throws InvalidSymbolException
     * @throws InvalidStateException
     * @throws MissingTransitionException
     */
    public function process(string $symbol): void
    {
        if (!\in_array($symbol, $this->alphabet, true)) {
            throw new InvalidSymbolException(\sprintf('Symbol "%s" is not in the alphabet.', $symbol));
        }

        try {
            $nextState = ($this->transitionFunction)($this->currentState, $symbol);
        } catch (\Error) {
            throw new MissingTransitionException(\sprintf('Transition error from state "%s" with symbol "%s".', self::stateToString($this->currentState), $symbol));
        }

        if (!\in_array($nextState, $this->states, true)) {
            throw new InvalidStateException(\sprintf('Transition function returned invalid state: "%s".', self::stateToString($nextState)));
        }

        $this->currentState = $nextState;
    }

    /**
     * Tokenize input string using greedy matching (longest match first).
     *
     * This method uses a greedy algorithm to parse the input string into symbols.
     * At each position, it tries to match the longest possible symbol from the alphabet.
     *
     * Example: For alphabet ['a', 'aa', 'aab'] and input 'aab':
     * - Returns ['aab'] (not ['aa', 'b'] or ['a', 'a', 'b'])
     *
     * @param string $input The input string to tokenize
     * @return array<int, string> Array of matched symbols
     *
     * @throws InvalidSymbolException
     */
    private function tokenizeInput(string $input): array
    {
        $tokens = [];
        $position = 0;
        $inputLength = \mb_strlen($input);

        while ($position < $inputLength) {
            $matched = false;

            foreach ($this->alphabet as $symbol) {
                $symbolLength = \mb_strlen($symbol);

                if ($position + $symbolLength <= $inputLength) {
                    $substring = \mb_substr($input, $position, $symbolLength);

                    if ($substring === $symbol) {
                        $tokens[] = $symbol;
                        $position += $symbolLength;
                        $matched = true;
                        break;
                    }
                }
            }

            if (!$matched) {
                $remainingInput = \mb_substr($input, $position);
                throw new InvalidSymbolException(
                    \sprintf('Cannot tokenize input at position %d. Remaining input: "%s"', $position, $remainingInput)
                );
            }
        }

        return $tokens;
    }

    /**
     * Process a sequence of input symbols and return the current state.
     *
     * @param string $input Sequence of input symbols as a string
     * @return int|string|bool|float|object|array<array-key, mixed>|null The current state after processing the sequence
     *
     * @throws InvalidSymbolException
     * @throws InvalidStateException
     * @throws MissingTransitionException
     */
    public function processSequence(string $input): int|string|bool|float|object|array|null
    {
        $savedState = $this->currentState;

        try {
            foreach ($this->tokenizeInput($input) as $symbol) {
                $this->process($symbol);
            }
        } catch (\Exception $e) {
            $this->currentState = $savedState;

            throw $e;
        }

        return $this->currentState;
    }

    public function reset(): void
    {
        $this->currentState = $this->initialState;
    }

    /**
     * Validate that all transitions (Q × Σ) return valid states.
     *
     * This method tests every possible combination of (state, symbol) to ensure
     * the transition function δ is properly defined for the entire domain Q × Σ.
     *
     * @throws InvalidStateException
     * @throws MissingTransitionException
     */
    public function validateAllTransitions(): void
    {
        $savedState = $this->currentState;

        try {
            foreach ($this->states as $state) {
                foreach ($this->alphabet as $symbol) {
                    try {
                        $nextState = ($this->transitionFunction)($state, $symbol);
                    } catch (\Error) {
                        throw new MissingTransitionException(
                            \sprintf('Transition function is not defined for state "%s" with symbol "%s".', self::stateToString($state), $symbol)
                        );
                    }

                    if (!\in_array($nextState, $this->states, true)) {
                        throw new InvalidStateException(
                            \sprintf('Transition from state "%s" with symbol "%s" returns invalid state "%s".', self::stateToString($state), $symbol, self::stateToString($nextState))
                        );
                    }
                }
            }
        } finally {
            $this->currentState = $savedState;
        }
    }

    /**
     * @return array<int, int|string|bool|float|object|array<array-key, mixed>|null>
     */
    public function getStates(): array
    {
        return $this->states;
    }

    /**
     * @return array<string>
     */
    public function getAlphabet(): array
    {
        return $this->alphabet;
    }

    /**
     * @return int|string|bool|float|object|array<array-key, mixed>|null
     */
    public function getInitialState(): int|string|bool|float|object|array|null
    {
        return $this->initialState;
    }

    /**
     * @return array<int, int|string|bool|float|object|array<array-key, mixed>|null>
     */
    public function getFinalStates(): array
    {
        return $this->finalStates;
    }

    /**
     * @return int|string|bool|float|object|array<array-key, mixed>|null
     */
    public function getCurrentState(): int|string|bool|float|object|array|null
    {
        return $this->currentState;
    }

    /**
     * Convert a state to its string representation for display in error messages.
     *
     * @param int|string|bool|float|object|array<array-key, mixed>|null $state
     */
    public static function stateToString(int|string|bool|float|object|array|null $state): string
    {
        if (\is_bool($state)) {
            return $state ? 'true' : 'false';
        }

        if (\is_scalar($state)) {
            return (string) $state;
        }

        if (\is_object($state)) {
            if ($state instanceof \Stringable || \method_exists($state, '__toString')) {
                return (string) $state;
            }
            return \get_class($state);
        }

        if (\is_array($state)) {
            return 'Array';
        }

        return \gettype($state);
    }
}
