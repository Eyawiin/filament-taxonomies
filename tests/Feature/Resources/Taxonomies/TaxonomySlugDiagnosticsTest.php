<?php

use Eyawiin\FilamentTaxonomies\Forms\TaxonomySlugValidation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

it('recognizes the known MySQL slug index and preserves the field path', function (string $table, bool $qualified): void {
    $index = $table === 'taxonomies' ? 'taxonomies_slug_unique' : 'taxonomy_terms_taxonomy_id_slug_unique';
    $driver = new PDOException('Driver failure');
    $driver->errorInfo = ['23000', 1062, "Duplicate entry 'example' for key '" . ($qualified ? $table . '.' : '') . $index . "'"];
    $exception = new UniqueConstraintViolationException('testing', 'insert into ' . $table . ' (slug) values (?)', ['example'], $driver);

    try {
        TaxonomySlugValidation::report($exception, $table, 'mountedActions.0.data.slug');
        $this->fail('Expected field validation.');
    } catch (ValidationException $validation) {
        expect($validation->errors())->toBe([
            'mountedActions.0.data.slug' => [__('validation.unique', ['attribute' => 'slug'])],
        ]);
    }
})->with(['taxonomies', 'taxonomy_terms'])->with(['plain index' => false, 'table-qualified index' => true]);

it('leaves unrecognized or misleading uniqueness diagnostics intact', function (array $info, string $sql): void {
    $driver = new PDOException('Driver failure');
    $driver->errorInfo = $info;
    $exception = new UniqueConstraintViolationException('testing', $sql, ['taxonomies_slug_unique'], $driver);

    try {
        TaxonomySlugValidation::report($exception, 'taxonomies', 'data.slug');
        $this->fail('Expected the original exception.');
    } catch (UniqueConstraintViolationException $actual) {
        expect($actual)->toBe($exception);
    }
})->with([
    'primary key' => [['23000', 1062, "Duplicate entry '1' for key 'taxonomies.PRIMARY'"], 'insert into taxonomies (id) values (?)'],
    'other index' => [['23000', 1062, "Duplicate entry 'name' for key 'taxonomies_name_unique'"], 'update taxonomies set name = ?'],
    'other table' => [['23000', 1062, "Duplicate entry 'example' for key 'taxonomies_slug_unique'"], 'insert into audit (message) values (?)'],
    'different driver code' => [['23000', 1048, "Duplicate entry 'example' for key 'taxonomies_slug_unique'"], 'insert into taxonomies (slug) values (?)'],
    'sqlite other column' => [['23000', 19, 'UNIQUE constraint failed: taxonomies.id'], 'insert into taxonomies (id) values (?)'],
    'missing diagnostics' => [[], 'insert into taxonomies (slug) values (?)'],
    'bound diagnostic text' => [['23000', 1062, "Duplicate entry 'for key taxonomies_slug_unique' for key 'audit_unique'"], 'insert into taxonomies (slug) values (?)'],
]);
