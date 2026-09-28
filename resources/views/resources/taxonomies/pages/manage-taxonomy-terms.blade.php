<x-filament-panels::page>
    @php
        $tree = $this->getTermTree();
    @endphp

    @if ($tree === [])
        <p class="text-sm text-gray-600 dark:text-gray-400">
            This taxonomy does not have any terms yet.
        </p>
    @else
        <ul class="space-y-3">
            @include(
                'filament-taxonomies::resources.taxonomies.partials.term-tree',
                ['nodes' => $tree]
            )
        </ul>
    @endif
</x-filament-panels::page>