<?php

return [
    'meta_key' => 'jetpk_theme_palette',

    'keys' => [
        'primary',
        'accent',
        'success',
        'page_bg',
        'surface',
        'text',
        'text_muted',
        'border',
    ],

    'labels' => [
        'primary' => 'Primary Action',
        'accent' => 'Secondary Accent',
        'success' => 'Success',
        'page_bg' => 'Page Background',
        'surface' => 'Card and Panel Surface',
        'text' => 'Primary Text',
        'text_muted' => 'Muted Text',
        'border' => 'Borders and Dividers',
    ],

    'helpers' => [
        'primary' => 'Main buttons such as Search, Continue, Save, Register and Confirm.',
        'accent' => 'Links, active tabs, badges, highlights and smaller actions.',
        'success' => 'Approved, paid, confirmed and completed states.',
        'page_bg' => 'Main page background for this theme.',
        'surface' => 'Cards, forms, dropdowns and dashboard panels.',
        'text' => 'Headings and main content.',
        'text_muted' => 'Descriptions, labels and secondary information.',
        'border' => 'Inputs, card outlines, tables and separators.',
    ],

    'defaults' => [
        'day' => [
            'primary' => '#63B32E',
            'accent' => '#19A7A6',
            'success' => '#63B32E',
            'page_bg' => '#EDF3F7',
            'surface' => '#FFFFFF',
            'text' => '#0B1D2A',
            'text_muted' => '#62788A',
            'border' => '#D7E2E9',
        ],
        'night' => [
            'primary' => '#63B32E',
            'accent' => '#32BDD1',
            'success' => '#7BD23F',
            'page_bg' => '#070F18',
            'surface' => '#0A1420',
            'text' => '#F1F6F9',
            'text_muted' => '#91A7B5',
            'border' => '#1C3445',
        ],
    ],

    'derived' => [
        'day' => [
            'primary_hover' => '#4F9423',
            'primary_active' => '#3F7820',
            'primary_soft' => '#EEF6E8',
            'primary_border' => '#C5DEB3',
            'accent_hover' => '#138C8C',
            'accent_soft' => '#E5F7F7',
            'success_hover' => '#4F9423',
            'surface_muted' => '#F6FAFC',
        ],
        'night' => [
            'primary_hover' => '#4F9423',
            'primary_active' => '#3F7820',
            'primary_soft' => '#1A2E12',
            'primary_border' => '#3D6B28',
            'accent_hover' => '#53CDE0',
            'accent_soft' => '#102B34',
            'success_hover' => '#91E553',
            'surface_muted' => '#0E1B28',
        ],
    ],

    'semantic' => [
        'warning' => '#F59E0B',
        'danger' => '#DC2626',
        'info' => '#0EA5E9',
    ],

    'legacy_orange_primary' => [
        '#EA7A1E',
        '#FB923C',
        '#F97316',
        '#FF7A00',
        '#FF8A00',
        '#F59433',
        '#FDBA74',
        '#F59E0B',
        '#1D4ED8',
        '#2563EB',
        '#16A34A',
    ],

    'legacy_system_primary' => [
        '#0F172A',
        '#0B1D2A',
        '#0B1A26',
        '#0C4A6E',
        '#1E293B',
        '#334155',
        '#475569',
        '#1E3A5F',
        '#3B82F6',
        '#60A5FA',
        '#0EA5E9',
        '#059669',
        '#10B981',
    ],

    /** Former approved Day/Night primaries — normalize to defaults.day.primary on read/reset. */
    'legacy_obsolete_day_primary' => [
        '#006B45',
        '#005638',
        '#00472F',
        '#007A52',
        '#46C96F',
        '#5EDB82',
        '#34B65D',
    ],

    'meta_day_customized_key' => 'jetpk_theme_palette_day_customized',

    'legacy_migration_target' => [
        'day' => '#63B32E',
        'night' => '#63B32E',
    ],
];
