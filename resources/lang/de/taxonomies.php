<?php

return [
    'resource' => [
        'model_label' => 'Taxonomie',
        'plural_model_label' => 'Taxonomien',
    ],
    'navigation' => [
        'taxonomies_group' => 'Taxonomien',
        'terms_count' => 'Anzahl der Begriffe in :name',
    ],
    'fields' => [
        'name' => 'Name',
        'name_helper' => 'Ein gut lesbarer Name, zum Beispiel Themen oder Schwierigkeit',
        'slug' => 'Slug',
        'terms_count' => 'Begriffe',
    ],
    'actions' => [
        'manage_terms' => 'Begriffe verwalten',
        'create_term' => 'Begriff erstellen',
        'edit_term' => 'Begriff bearbeiten',
        'edit_term_tooltip' => 'Begriff bearbeiten',
        'delete_term' => 'Begriff löschen',
        'delete_term_tooltip' => 'Begriff löschen',
        'delete_term_heading' => 'Begriff löschen',
        'delete_term_description' => 'Möchten Sie diesen Begriff wirklich löschen? Seine direkten Unterbegriffe rücken auf die oberste Ebene.',
        'delete_term_submit' => 'Löschen',
        'delete_term_failed' => 'Der Begriff konnte nicht gelöscht werden',
        'delete_term_rejected' => 'Begriff kann nicht gelöscht werden',
        'delete_taxonomy_failed' => 'Die Taxonomie konnte nicht gelöscht werden',
    ],
    'manage_terms' => [
        'title' => 'Taxonomiebegriffe verwalten',
        'heading' => 'Begriffe verwalten: :name',
        'section' => 'Begriffe',
        'empty' => 'In diesem Baum sind keine Begriffe verfügbar.',
        'expand_all' => 'Alle aufklappen',
        'collapse_all' => 'Alle zuklappen',
        'saving' => 'Verschiebung wird gespeichert …',
        'move_failed' => 'Die Verschiebung konnte nicht gespeichert werden. Laden Sie den Baum neu und versuchen Sie es erneut.',
        'drag' => 'Zum Sortieren ziehen',
        'move_denied' => 'Sie dürfen diesen Begriff nicht verschieben',
        'toggle_children' => 'Unterbegriffe ein- oder ausblenden',
        'move_up' => ':name nach oben verschieben',
        'move_down' => ':name nach unten verschieben',
        'first_term' => 'Bereits der erste Begriff auf dieser Ebene',
        'last_term' => 'Bereits der letzte Begriff auf dieser Ebene',
    ],
    'validation' => [
        'parent_unavailable' => 'Der gewählte übergeordnete Begriff ist nicht mehr verfügbar.',
        'placement' => 'Die Ablageposition muss davor, darin oder danach sein.',
        'position' => 'Die Position des Begriffs muss eine nicht negative ganze Zahl sein.',
    ],
];
