<?php

namespace App\Enums;

enum Ruleset: string
{
    case Generic = 'generic';
    case Srd5e2024 = 'srd-5e-2024';

    /**
     * The name a GM reads.
     *
     * CC BY 4.0 licenses the text of the SRD and grants no trademark rights, so this
     * names the document and never the game. Dungeons & Dragons and D&D are trademarks
     * of Wizards of the Coast LLC. CompendiumLicensingTest holds that line.
     */
    public function label(): string
    {
        return match ($this) {
            self::Generic => 'System agnostic',
            self::Srd5e2024 => 'SRD 5.2.1 (2024 rules)',
        };
    }

    /**
     * Whether a campaign on this ruleset has stat blocks to look up.
     *
     * The compendium screens, the API endpoints and the entity field all read this
     * rather than comparing cases, so a second ruleset with a dataset is one line.
     */
    public function hasCompendium(): bool
    {
        return $this === self::Srd5e2024;
    }
}
