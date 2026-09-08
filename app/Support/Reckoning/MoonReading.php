<?php

namespace App\Support\Reckoning;

/**
 * One moon on one night: its name and where it is in its cycle.
 */
final readonly class MoonReading
{
    public function __construct(
        public string $name,
        public MoonPhase $phase,
    ) {}

    /**
     * "Pale, full", the way the dashboard says it.
     */
    public function describe(): string
    {
        return $this->name.', '.strtolower($this->phase->label());
    }
}
