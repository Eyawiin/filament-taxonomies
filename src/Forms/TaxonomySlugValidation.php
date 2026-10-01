<?php

namespace Eyawiin\FilamentTaxonomies\Forms;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class TaxonomySlugValidation
{
    /**
     * Recognize only the package's existing slug constraints on SQLite/MySQL.
     * Inspect driver diagnostics, never the formatted SQL with user bindings.
     */
    public static function report(UniqueConstraintViolationException $exception, string $table, string $statePath): never
    {
        $columns = match ($table) {
            'taxonomies' => 'taxonomies.slug',
            'taxonomy_terms' => 'taxonomy_terms.taxonomy_id, taxonomy_terms.slug',
            default => null,
        };
        $index = match ($table) {
            'taxonomies' => 'taxonomies_slug_unique',
            'taxonomy_terms' => 'taxonomy_terms_taxonomy_id_slug_unique',
            default => null,
        };
        $info = $exception->errorInfo ?? [];
        $message = $info[2] ?? '';
        $writesTable = preg_match('/^(?:insert into|update) ["`]?' . preg_quote($table, '/') . '["`]?[ (]/i', $exception->getSql()) === 1;
        $sqlite = in_array((int) ($info[1] ?? 0), [19, 2067], true)
            && $message === 'UNIQUE constraint failed: ' . $columns;
        $mysql = ($info[0] ?? null) === '23000' && (int) ($info[1] ?? 0) === 1062
            && preg_match("/for key '(?:" . preg_quote($table, '/') . '\\.)?' . preg_quote($index ?? '', '/') . "'$/", $message) === 1;

        if ($columns === null || ! $writesTable || (! $sqlite && ! $mysql)) {
            throw $exception;
        }

        throw ValidationException::withMessages([
            $statePath => __('validation.unique', ['attribute' => 'slug']),
        ]);
    }
}
