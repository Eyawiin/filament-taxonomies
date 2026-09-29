<?php

namespace Eyawiin\FilamentTaxonomies\Enums;

enum TaxonomyTermDropPosition: string
{
    case Before = 'before';

    case Inside = 'inside';

    case After = 'after';
}
