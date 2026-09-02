<?php

namespace App\Actions\Mentions;

use App\Models\Entity;
use App\Models\Mention;
use Illuminate\Database\Eloquent\Model;

class RewriteWikiLinks
{
    /**
     * After a rename, rewrites [[Old]], [[Old|label]], and [[type:Old]] to the new name in every
     * source that mentions the entity. Labels and prefixes stay as they were.
     */
    public function handle(Entity $entity, string $oldName): void
    {
        if (mb_strtolower(trim($oldName)) === mb_strtolower($entity->name)) {
            return;
        }

        $pattern = '/\[\[((?:[A-Za-z]+:)?)\s*'.preg_quote($oldName, '/').'\s*(\||\]\])/iu';
        $newName = $entity->name;

        $mentions = Mention::withoutGlobalScopes()
            ->where('target_entity_id', $entity->id)
            ->with('source')
            ->get()
            ->unique(fn (Mention $mention) => $mention->source_type.'|'.$mention->source_id);

        foreach ($mentions as $mention) {
            $source = $mention->source_type === $entity->getMorphClass() && $mention->source_id === $entity->id
                ? $entity
                : $mention->source;

            if (! $source instanceof Model || ! method_exists($source, 'mentionableFields')) {
                continue;
            }

            foreach ($source->mentionableFields() as $field) {
                $markdown = $source->getAttribute($field);

                if (! is_string($markdown) || $markdown === '') {
                    continue;
                }

                $rewritten = preg_replace_callback(
                    $pattern,
                    fn (array $match) => '[['.$match[1].$newName.$match[2],
                    $markdown,
                );

                if ($rewritten !== null && $rewritten !== $markdown) {
                    $source->setAttribute($field, $rewritten);
                }
            }

            if ($source->isDirty()) {
                $source->saveQuietly();
            }
        }

        Mention::withoutGlobalScopes()
            ->where('target_entity_id', $entity->id)
            ->update(['target_name' => $newName]);
    }
}
