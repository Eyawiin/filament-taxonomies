<?php

use Eyawiin\FilamentTaxonomies\Forms\TaxonomySelect;
use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Services\TaxonomyTreeService;
use Filament\Schemas\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema as DatabaseSchema;

function verifyConsumerFields(): void
{
    DatabaseSchema::create('consumer_decks', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
    });
    $service = app(TaxonomyTreeService::class);
    foreach (['topics', 'levels'] as $kind) {
        $taxonomy = Taxonomy::create(['name' => 'Consumer field ' . $kind, 'slug' => 'consumer-field-' . $kind]);
        $root = $service->createTerm($taxonomy, ucfirst($kind), $kind);
        $group = $service->createTerm($taxonomy, 'Group', $kind . '-group', $root);
        $child = $service->createTerm($taxonomy, ucfirst($kind) . ' leaf', $kind . '-leaf', $group);
        $field = TaxonomySelect::make($kind)->taxonomy($taxonomy)->multiple($kind === 'topics')->container(Schema::make());
        requireConsumer(count($field->getNodes()) === 3, 'Copied consumer taxonomy field did not project its tree.');
        requireConsumer($field->acceptsSelection($kind === 'topics' ? [$child->id] : $child->id), 'Copied consumer field rejected valid selection.');
        requireConsumer(! $field->acceptsSelection($kind === 'topics' ? [true] : true), 'Copied consumer field accepted a boolean identity.');
    }
    echo "PASS copied consumer single/multiple field API and nested options.\n";
}
