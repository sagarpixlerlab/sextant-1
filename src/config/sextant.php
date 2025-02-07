<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Possible global scopes
    |--------------------------------------------------------------------------
    | Define global scopes to pass through checks.
    | To be accessible all scopes should be described in extraScopes method.
    |
    */
    'global_scopes' => [
        'withTrashed', 'withoutTrashed', 'onlyTrashed'
    ],

    /*
    |--------------------------------------------------------------------------
    | Search engine settings
    |--------------------------------------------------------------------------
    | Settings for search engine.
    | Type could be: strict, words. Default type: strict.
    |
    */
    'search' => [
        'type' => 'strict',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default request key map.
    |--------------------------------------------------------------------------
    | You can add and change your own keys map. Map used by other packages to determine attachable request key names.
    | IMPORTANT: don't forget to change valid key name in drivers section.
    */
    'map'     => [
        'expand'       => 'expand',
        'filter'       => 'filter',
        'sort'         => 'sort',
        'search'       => 'search',
        'scopes'       => 'scopes',
        'filterExpand' => 'filterExpand',
        'sortExpand'   => 'sortExpand',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default filter drivers.
    |--------------------------------------------------------------------------
    | You can add your own driver or rename keys on exists.
    |
    */
    'drivers' => [
        [
            'class'       => \Amondar\Sextant\Library\Actions\Expand::class,
            'request_key' => 'expand',
        ],
        [
            'class'       => \Amondar\Sextant\Library\Actions\Filter::class,
            'request_key' => 'filter',
        ],
        [
            'class'       => \Amondar\Sextant\Library\Actions\Sort::class,
            'request_key' => 'sort',
        ],
        [
            'class'       => \Amondar\Sextant\Library\Actions\Search::class,
            'request_key' => 'search',
        ],
        [
            'class'       => \Amondar\Sextant\Library\Actions\Scope::class,
            'request_key' => 'scopes',
        ],
        [
            [
                'class'       => \Amondar\Sextant\Library\Actions\Filter::class,
                'request_key' => 'filterExpand',
            ],
            [
                'class'       => \Amondar\Sextant\Library\Actions\Sort::class,
                'request_key' => 'sortExpand',
            ],
        ],

    ],
];