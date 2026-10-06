<section class="taxonomy-removal-preview" aria-label="{{ $configuration['labels']['removal_preview'] }}">
    <h3 id="{{ $getId() }}-removal-title" x-text="text('removal_title', { count: removal.ids.length })"></h3>
    <p x-text="labels.removal_help"></p>
    <ul>
        <template x-for="id in removal.ids" :key="String(id)">
            <li><strong dir="auto" x-text="node(id)?.name"></strong><span dir="auto" x-text="pathLabel(id)"></span></li>
        </template>
    </ul>
    <div class="taxonomy-removal-actions">
        <x-filament::button color="gray" x-on:click="cancelRemoval()">{{ $configuration['labels']['cancel_removal'] }}</x-filament::button>
        <x-filament::button color="danger" data-removal-confirm x-bind:disabled="blocked" x-on:click="confirmRemoval()"><span x-text="text('confirm_removal', { count: removal.ids.length })"></span></x-filament::button>
    </div>
</section>
