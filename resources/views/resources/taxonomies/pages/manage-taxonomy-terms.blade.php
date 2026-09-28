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

            <div x-data>
                <div class="mb-4 flex justify-end gap-2">
                    <x-filament::button
                        type="button"
                        size="sm"
                        color="gray"
                        icon="heroicon-o-arrows-pointing-out"
                        x-on:click="$dispatch('taxonomy-tree-set-expanded', { expanded: true })"
                    >
                        Expand All
                    </x-filament::button>

                    <x-filament::button
                        type="button"
                        size="sm"
                        color="gray"
                        icon="heroicon-o-arrows-pointing-in"
                        x-on:click="$dispatch('taxonomy-tree-set-expanded', { expanded: false })"
                    >
                        Collapse All
                    </x-filament::button>
                </div>

                <x-filament-taxonomies::taxonomy-tree :nodes="$tree" />
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>