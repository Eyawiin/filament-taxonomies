<?php

namespace Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages;

use Eyawiin\FilamentTaxonomies\Forms\TaxonomySlugValidation;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\TaxonomyResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;

class EditTaxonomy extends EditRecord
{
    protected static string $resource = TaxonomyResource::class;

    public function hydrate(): void
    {
        // Restore the resource query scope before Filament authorizes subsequent requests.
        $this->record = $this->resolveRecord($this->getRecord()->getRouteKey());

        parent::hydrate();
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return parent::handleRecordUpdate($record, $data);
        } catch (UniqueConstraintViolationException $exception) {
            TaxonomySlugValidation::report($exception, 'taxonomies', $this->form->getStatePath() . '.slug');
        }
    }
}
