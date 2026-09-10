# Attribution

The creature data in `srd-5.2.1-creatures.json` is SRD content. It is not demgem's
work, it is not MIT licensed, and this notice travels with it.

## The notice

> This work includes material taken from the System Reference Document 5.2.1
> ("SRD 5.2.1") by Wizards of the Coast LLC and is licensed under the Creative
> Commons Attribution 4.0 International License, available at
> https://creativecommons.org/licenses/by/4.0/legalcode.

`config/compendium.php` holds this string, and it is the one the application renders.
Change it there and it changes everywhere: the compendium screens, every stat block
page, and every stat block the API returns.

## Where it has to appear

CC BY 4.0 asks for credit wherever the material is shared or shown. In demgem that
means all four of these, and a change to any of them needs the notice to come with it.

1. The compendium index and every stat block page, in the footer.
2. Every stat block document the API returns, as the `attribution` key.
3. This file and `LICENSE-CC-BY-4.0.txt`, in the repository.
4. The README's Content licensing section.

A campaign export is deliberately not on that list. It carries a stat block as a
`{ruleset, slug}` reference and never its prose, so an export redistributes no SRD
material and needs no notice of its own.

## The trademark is not licensed

CC BY 4.0 licenses the text. It grants no trademark rights at all.

Dungeons & Dragons, D&D, and their logos are trademarks of Wizards of the Coast LLC.
No user-visible string in demgem uses them, nothing here implies that Wizards of the
Coast endorses demgem, and `CompendiumLicensingTest` fails the build if one appears.
The ruleset is called "SRD 5.2.1 (2024 rules)" for that reason and no other.

## Scope

Only the SRD is licensed. Creatures outside it — a beholder, a mind flayer, anything
from a published book or a setting — carry no licence and must never be added to this
dataset. `database/srd/README.md` records where the file came from so that swapping in
a dataset of mixed provenance is a deliberate act rather than an accident.
