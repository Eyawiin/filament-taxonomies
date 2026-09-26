<?php

namespace Eyawiin\FilamentTaxonomies\Commands;

use Illuminate\Console\Command;

class FilamentTaxonomiesCommand extends Command
{
    public $signature = 'filament-taxonomies';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
