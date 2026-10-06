<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div class="taxonomy-parent-tree taxonomy-assignment-field" wire:key="{{ $getId() }}-assignment-picker"
        x-load
        x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('taxonomy-assignment-picker', package: 'eyawiin/filament-taxonomies') }}"
        x-data="taxonomyAssignmentPicker({ state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$getStatePath()}')") }}, ...@js($configuration) })">
        <span hidden data-tree-config="{{ json_encode($configuration) }}"></span>
        <x-filament::input.wrapper :disabled="$isDisabled()" alpine-disabled="disabled" :valid="! $errors->has($getStatePath())">
            <button id="{{ $getId() }}" x-ref="trigger" type="button" class="taxonomy-parent-trigger"
                aria-haspopup="dialog" aria-controls="{{ $getId() }}-picker" x-bind:aria-expanded="open"
                x-bind:aria-disabled="blocked" x-bind:data-readonly="readOnly" x-bind:disabled="disabled"
                @disabled($isDisabled()) x-on:click="show()" x-on:keydown.arrow-down.prevent="show()">
                <span dir="auto" x-text="selectedTerms.length ? selectionFeedback : labels.choose"></span>
                <span class="taxonomy-picker-trigger-action">
                    <span x-text="labels.edit_selection"></span>
                    <x-filament::icon icon="heroicon-m-chevron-down" aria-hidden="true" />
                </span>
            </button>
        </x-filament::input.wrapper>

        <section class="taxonomy-selection-review" x-show="selectedTerms.length" x-cloak aria-label="{{ $configuration['labels']['review'] }}">
            <h2 class="taxonomy-sr-only" x-ref="reviewHeading" tabindex="-1" x-text="reviewId === null ? labels.review : node(reviewId)?.name">{{ $configuration['labels']['review'] }}</h2>
            <div class="taxonomy-review-heading">
                <x-filament::button color="gray" size="sm" icon="heroicon-m-chevron-left" class="taxonomy-path-back"
                    x-bind:disabled="reviewId === null" x-on:click="review(node(reviewId)?.ancestors.at(-1) ?? null)">
                    {{ $configuration['labels']['back'] }}
                </x-filament::button>
                @include('filament-taxonomies::forms.assignment-path', ['pathId' => 'reviewId', 'pathAction' => 'review', 'pathRoot' => 'review', 'pathRef' => 'reviewPath', 'pathClass' => 'taxonomy-review-path'])
            </div>
            <div class="taxonomy-review-rows" x-ref="reviewRows">
                <template x-for="term in reviewRows" :key="term.id">
                    <div class="taxonomy-review-row" :data-review-id="term.id" :data-context="!isSelected(term.id)" :style="{ '--review-level': term.level }">
                        <span dir="auto" class="taxonomy-review-name" x-text="term.name"></span>
                        <span class="taxonomy-review-context" x-show="!isSelected(term.id)" x-text="labels.context"></span>
                        <div class="taxonomy-review-actions">
                            <x-filament::link tag="button" color="gray" class="taxonomy-review-browse" icon="heroicon-m-chevron-right" icon-position="after"
                                x-show="term.deeper" x-on:click="review(term.id)" x-bind:aria-label="text('browse_selected', { name: term.name })">
                                <span dir="auto" x-text="text('view_below', { count: selectedBelowCount(term.id) })"></span>
                            </x-filament::link>
                            <x-filament::link tag="button" color="gray" class="taxonomy-review-remove"
                                x-show="isSelected(term.id) && includesAncestors && selectedBelowCount(term.id)"
                                x-bind:disabled="!canRemove(term.id)" x-bind:aria-label="removeAction(term.id)"
                                x-bind:title="canRemove(term.id) ? removeAction(term.id) : labels.denied" x-on:click="requestRemoval(term.id)">
                                <span x-text="text('remove_branch_count', { count: selectedBelowCount(term.id) + 1 })"></span>
                            </x-filament::link>
                            <x-filament::icon-button color="gray" icon="heroicon-m-x-mark" class="taxonomy-review-remove"
                                label="{{ $configuration['labels']['remove_term'] }}" x-show="isSelected(term.id) && !(includesAncestors && selectedBelowCount(term.id))"
                                x-bind:disabled="!canRemove(term.id)" x-bind:aria-label="removeAction(term.id)"
                                x-bind:title="canRemove(term.id) ? removeAction(term.id) : labels.denied" x-on:click="requestRemoval(term.id)" />
                        </div>
                    </div>
                </template>
            </div>
            <x-filament::link tag="button" color="gray" x-show="reviewMatches.length > reviewLimit" x-on:click="reviewLimit += 50">
                {{ $configuration['labels']['show_more'] }}
            </x-filament::link>
            <template x-for="term in unavailableTerms" :key="String(term.id)">
                <p class="taxonomy-review-unavailable" x-text="term.name"></p>
            </template>
        </section>
        <div class="taxonomy-assignment-notice taxonomy-field-notice">
            <span role="status" x-text="!open ? notice : ''"></span>
            <x-filament::link tag="button" color="gray" x-show="!open && undo && undo.scope === 'state'" x-bind:disabled="blocked" x-on:click="undoRemoval()">
                {{ $configuration['labels']['undo'] }}
            </x-filament::link>
        </div>

        <dialog id="{{ $getId() }}-picker" x-ref="dialog" class="taxonomy-assignment-dialog" aria-labelledby="{{ $getId() }}-picker-title"
            wire:ignore.self x-on:cancel.prevent.stop="close()" x-on:keydown.tab="trapFocus($event)"
            x-on:keydown.escape.stop x-on:keydown.enter="if ($event.target.matches('input')) $event.preventDefault()">
            <div class="taxonomy-picker-content">
                <header class="taxonomy-picker-header">
                    <h2 id="{{ $getId() }}-picker-title">{{ $configuration['labels']['choose'] }}</h2>
                    <x-filament::icon-button color="gray" icon="heroicon-m-x-mark" label="{{ $configuration['labels']['close'] }}"
                        class="taxonomy-picker-close" x-on:click="close()" />
                </header>
                <p class="taxonomy-picker-help" x-show="includesAncestors" x-text="labels.ancestor_help"></p>
                <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                    <x-filament::input x-ref="search" type="search" x-model="search"
                        placeholder="{{ $configuration['labels']['search'] }}" aria-label="{{ $configuration['labels']['search_label'] }}" />
                </x-filament::input.wrapper>
                <div class="taxonomy-picker-navigation">
                    <x-filament::button color="gray" size="sm" icon="heroicon-m-chevron-left" class="taxonomy-path-back"
                        x-bind:disabled="browseId === null && !search" x-on:click="browse(search ? browseId : node(browseId)?.ancestors.at(-1) ?? null)">
                        {{ $configuration['labels']['back'] }}
                    </x-filament::button>
                    @include('filament-taxonomies::forms.assignment-path', ['pathId' => 'browseId', 'pathAction' => 'browse', 'pathRoot' => 'all_terms', 'pathRef' => 'pickerPath', 'pathClass' => 'taxonomy-picker-path'])
                </div>
                <div class="taxonomy-picker-options" x-ref="options" aria-label="{{ $configuration['labels']['tree_label'] }}">
                    <template x-for="term in pickerRows" :key="term.id">
                        <div class="taxonomy-assignment-row" :data-assignment-row="term.id">
                            <label class="taxonomy-assignment-choice">
                                <x-filament::input.checkbox x-bind:data-assignment-id="term.id" x-bind:data-assignment-name="term.name"
                                    x-bind:aria-label="term.name" x-bind:aria-describedby="term.disabled || (search && term.ancestors.length) ? '{{ $getId() }}-term-details-' + term.id : null"
                                    x-bind:checked="has(term.id, 'draft')" x-bind:disabled="blocked || term.disabled"
                                    x-on:change="toggleDraft(term.id); $event.target.checked = has(term.id, 'draft')" />
                                <span class="taxonomy-assignment-term">
                                    <span dir="auto" x-text="term.name"></span>
                                    <span :id="'{{ $getId() }}-term-details-' + term.id">
                                        <span dir="auto" class="taxonomy-assignment-search-path" x-show="search && term.ancestors.length" x-text="pathLabel(term.id)"></span>
                                        <span class="taxonomy-parent-reason" x-show="term.disabled" x-text="term.reason || labels.denied"></span>
                                    </span>
                                </span>
                            </label>
                            <span class="taxonomy-parent-summary" x-bind:title="draftBelow(term.id) ? text('selected_below', { count: draftBelow(term.id) }) : null">
                                <span class="taxonomy-summary-full" x-text="draftBelow(term.id) ? text('selected_below', { count: draftBelow(term.id) }) : ''"></span>
                                <span class="taxonomy-summary-compact" x-text="draftBelow(term.id) ? text('below', { count: draftBelow(term.id) }) : ''"></span>
                            </span>
                            <x-filament::button color="gray" size="sm" icon="heroicon-m-chevron-right" icon-position="after" class="taxonomy-picker-browse"
                                x-show="term.hasChildren" x-bind:aria-label="text('browse', { name: term.name })" x-on:click="browse(term.id)">
                                {{ $configuration['labels']['edit_selection'] }}
                            </x-filament::button>
                        </div>
                    </template>
                    <p class="taxonomy-parent-empty" x-show="!pickerMatches.length" x-text="labels.empty"></p>
                    <x-filament::link tag="button" color="gray" class="taxonomy-show-more" x-show="pickerMatches.length > listLimit" x-on:click="listLimit += 50">
                        {{ $configuration['labels']['show_more'] }}
                    </x-filament::link>
                </div>
                <div class="taxonomy-picker-status">

                    <div class="taxonomy-assignment-notice">
                        <span dir="auto" role="status" x-text="notice || text('selected', { count: draft.length })"></span>
                        <x-filament::link tag="button" color="gray" x-show="undo && undo.scope === 'draft'" x-bind:disabled="blocked" x-on:click="undoRemoval()">
                            {{ $configuration['labels']['undo'] }}
                        </x-filament::link>
                    </div>
                </div>
                <footer class="taxonomy-picker-footer">
                    <x-filament::link tag="button" color="gray" x-bind:disabled="blocked || !draft.length" x-on:click="clearDraft()">
                        {{ $configuration['labels']['clear'] }}
                    </x-filament::link>
                    <div>
                        <x-filament::button color="gray" x-on:click="close()">{{ $configuration['labels']['cancel'] }}</x-filament::button>
                        <x-filament::button x-bind:disabled="blocked" x-on:click="apply()">{{ $configuration['labels']['apply'] }}</x-filament::button>
                    </div>
                </footer>
            </div>
        </dialog>
        <dialog x-ref="removalDialog" class="taxonomy-removal-dialog" aria-labelledby="{{ $getId() }}-removal-title"
            wire:ignore.self x-on:cancel.prevent.stop="cancelRemoval()" x-on:keydown.tab="trapFocus($event)" x-on:keydown.escape.stop>
            <template x-if="removal">
                @include('filament-taxonomies::forms.assignment-removal')
            </template>
        </dialog>
    </div>
</x-dynamic-component>
