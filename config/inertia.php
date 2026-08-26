<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    |
    | This app keeps its page components in `resources/js/Pages` (capital P),
    | but inertia-laravel's packaged default points at `resources/js/pages`
    | (lowercase). On Windows that difference is invisible, because the
    | filesystem is case-insensitive; on Linux — CI and the production server —
    | the view finder simply does not find anything.
    |
    | The visible symptom was `assertInertia()->component('Tracciabilita')`
    | failing in CI with "Inertia page component file [Tracciabilita] does not
    | exist" while passing locally on Windows.
    |
    | Note this only ever affected tests: `pages.ensure_pages_exist` is false by
    | default, so the runtime render path never consulted the finder. It is
    | `testing.ensure_pages_exist` (default true) that does.
    |
    | The whole `pages` block is restated because Laravel's mergeConfigFrom is a
    | shallow merge — defining only `paths` here would drop `extensions` and
    | `ensure_pages_exist` from the package defaults.
    |
    */

    'pages' => [

        'ensure_pages_exist' => false,

        'paths' => [

            resource_path('js/Pages'),

        ],

        'extensions' => [

            'js',
            'jsx',
            'svelte',
            'ts',
            'tsx',
            'vue',

        ],

    ],

];
