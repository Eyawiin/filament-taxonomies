<x-filament-panels::page>
    @php
        $tree = $this->getTermTree();
    @endphp

    @if ($tree === [])
        <p class="text-sm text-gray-600 dark:text-gray-400">
            {{ __('filament-taxonomies::taxonomies.manage_terms.empty') }}
        </p>
    @else
        <x-filament::section>
            <x-slot name="heading">
                {{ __('filament-taxonomies::taxonomies.manage_terms.section') }}
            </x-slot>

            <div
                x-load
                x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc(
                    'taxonomy-tree',
                    package: 'eyawiin/filament-taxonomies',
                ) }}"
                x-data="taxonomyTreeDrag({
                    dropTerm: (termId, targetId, placement) =>
                        $wire.dropTerm(termId, targetId, placement),
                    moveFailed: @js(__('filament-taxonomies::taxonomies.manage_terms.move_failed')),
                })"
                x-bind:aria-busy="saving"
                x-on:taxonomy-tree-move-term="
                    drop($event.detail.termId, $event.detail.targetId, $event.detail.placement)
                "
            >
                <div class="mb-4 flex items-center justify-end gap-2">
                    <span
                        class="me-auto text-sm text-gray-500"
                        role="status"
                        x-text="saving ? @js(__('filament-taxonomies::taxonomies.manage_terms.saving')) : ''"
                    ></span>

                    <x-filament::button
                        type="button"
                        size="sm"
                        color="gray"
                        icon="heroicon-o-arrows-pointing-out"
                        x-on:click="$dispatch('taxonomy-tree-set-expanded', { expanded: true })"
                    >
                        {{ __('filament-taxonomies::taxonomies.manage_terms.expand_all') }}
                    </x-filament::button>

                    <x-filament::button
                        type="button"
                        size="sm"
                        color="gray"
                        icon="heroicon-o-arrows-pointing-in"
                        x-on:click="$dispatch('taxonomy-tree-set-expanded', { expanded: false })"
                    >
                        {{ __('filament-taxonomies::taxonomies.manage_terms.collapse_all') }}
                    </x-filament::button>
                </div>

                <p class="mb-3 text-sm text-danger-600" role="alert" x-show="error" x-text="error" x-cloak></p>

                @foreach (['placement', 'drop', 'move'] as $errorKey)
                    @error($errorKey)
                        <p class="mb-3 text-sm text-danger-600" role="alert">{{ $message }}</p>
                    @enderror
                @endforeach

                <x-filament-taxonomies::taxonomy-tree :nodes="$tree" />
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>