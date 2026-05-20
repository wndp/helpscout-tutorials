<?php

return [

    /*
     * HelpScout Docs API key. Used as the Basic Auth username; HelpScout requires
     * a dummy password (this package uses "X").
     */
    'api_key' => env('HELPSCOUT_DOCS_API_KEY'),

    /*
     * Subdirectory under storage_path() where downloaded articles and the
     * manifest live. Override per-command with the --path option.
     */
    'storage_path' => env('HELPSCOUT_TUTORIALS_PATH', 'helpscout-tutorials'),

    /*
     * If set, tutorials:download and tutorials:create will use this collection
     * by default when --collection is not passed. Matched against the
     * collection name or slug (case-insensitive).
     */
    'default_collection' => env('HELPSCOUT_DEFAULT_COLLECTION'),

];
