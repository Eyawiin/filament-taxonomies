<x-filament-panels::page>
    @php
        $tree = $this->getTermTree();
    @endphp

    @if ($tree === [])
        <p class="text-sm text-gray-600 dark:text-gray-400">
            This taxonomy does not have any terms yet.
        </p>
    @else
        <x-filament::section>
            <x-slot name="heading">
                Terms
            </x-slot>

            <x-filament-taxonomies::taxonomy-tree :nodes="$tree" />
        </x-filament::section>
    @endif
</x-filament-panels::page>