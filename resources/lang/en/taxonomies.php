<?php

return [
    'resource' => [
        'model_label' => 'taxonomy',
        'plural_model_label' => 'taxonomies',
    ],
    'navigation' => [
        'taxonomies_group' => 'Taxonomies',
        'terms_count' => 'Number of terms in :name',
    ],
    'fields' => [
        'name' => 'Name',
        'name_helper' => 'A readable name, such as Topics or Difficulty',
        'slug' => 'Slug',
        'terms_count' => 'Terms',
    ],
    'actions' => [
        'manage_terms' => 'Manage Terms',
        'create_term' => 'Create Term',
        'edit_term' => 'Edit Term',
        'edit_term_tooltip' => 'Edit term',
        'delete_term' => 'Delete Term',
        'delete_term_tooltip' => 'Delete term',
        'delete_term_heading' => 'Delete term',
        'delete_term_description' => 'Are you sure you want to delete this term? Its direct children will become root terms.',
        'delete_term_submit' => 'Delete',
        'delete_term_failed' => 'The term could not be deleted',
        'delete_term_rejected' => 'Unable to delete term',
        'delete_taxonomy_failed' => 'The taxonomy could not be deleted',
    ],
    'manage_terms' => [
        'title' => 'Manage Taxonomy Terms',
        'heading' => 'Manage Terms: :name',
        'section' => 'Terms',
        'empty' => 'No terms are available in this tree.',
        'expand_all' => 'Expand All',
        'collapse_all' => 'Collapse All',
        'saving' => 'Saving move…',
        'move_failed' => 'The move could not be saved. Refresh the tree and try again.',
        'drag' => 'Drag to reorder',
        'move_denied' => 'You do not have permission to move this term',
        'toggle_children' => 'Toggle children',
        'move_up' => 'Move :name up',
        'move_down' => 'Move :name down',
        'first_term' => 'Already the first term at this level',
        'last_term' => 'Already the last term at this level',
    ],
    'validation' => [
        'parent_unavailable' => 'The selected parent is no longer available.',
        'placement' => 'The drop placement must be before, inside, or after.',
        'position' => 'The term position must be a non-negative integer.',
    ],
];
