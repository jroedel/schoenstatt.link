<?php
use Schoenstatt\Model\SchoenstattTable;

$env = getenv('APP_ENV') ?: 'production';
$inDevelopment = $env !== 'production';
//the connect-src is for /api/v1 for example
if ($inDevelopment) {
    //the sha256 sums are for css by the zend-developer-toolbar
    $csp = "default-src 'none'; script-src 'self' 'nonce-{:nonce}' 'report-sample'; img-src 'self' a.tile.openstreetmap.org data: https:; style-src 'self' 'sha256-vHSqqVrJZ5Qsgn51E6GCa6KthouKZttn9iJIjW7FpsY=' 'sha256-43LT1ORp2cNKCkTTWnjODmQQz1f0cDG/9/QwP7+B2EE='; connect-src 'self'; font-src 'self'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'; report-uri http://schoenstatt.localhost/csp-report.php;";
} else {
    $csp = "default-src 'none'; script-src 'self' 'nonce-{:nonce}' 'report-sample'; img-src 'self' a.tile.openstreetmap.org https:; style-src 'self'; connect-src 'self'; font-src 'self'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'; report-uri https://schoenstatt.link/csp-report.php;";
}

return [
    'sion_model' => [
        /**
         * An AuthenticationServiceInterface instance to be fetched from service manager
         */
        'default_authentication_service' => 'zfcuser_auth_service',
        /**
         * Public base URL, no trailing slash. Only bin/console reads this: a CLI
         * process has no request to infer the host from, and
         * cache:flush-persistent has to reach the web SAPI over HTTP because an
         * APCu segment belongs to the SAPI that created it.
         */
        'canonical_base_url' => $inDevelopment ? 'http://localhost' : 'https://schoenstatt.link',
        //config for Content Security Policy
        'csp_config' => [
            //https://csp-evaluator.withgoogle.com
            'csp_string' => $csp,
            //if this header isn't set, no Content-Security-Policy header will be set
            'inject_headers_event' => \Laminas\Mvc\MvcEvent::EVENT_RENDER,
        ],
        /**
         * Database table name of where to store change records
         */
        'changes_table' => 'sch_changes',
        /**
         * Database table name of where to store visit records
         */
        'visits_table' => 'sch_visits',
        /**
         * This is the service name of a SionTable instance to call the getChanges() method
         */
        'changes_model' => SchoenstattTable::class,
        /**
         * Used for auto-generating navigation pages for breadcrumbs
         */
        'navigation_key' => 'default',

        'changes_show_all' => true,
        'visits_model' => SchoenstattTable::class,
        'files_directory' => 'data/files',
        'public_files_directory' => 'public/files',
//        'post_place_line_format' => ':zip :cityState',
//        'post_place_line_format_by_country' => [
//            'US' => ':cityState :zip',
//            'CL' => ':cityState :zip',
//        ],

        'default_redirect_route' => 'welcome',

        /**
        * If this key is set, it will be used to prime the SionForm with persons for suggestions.
        * The class must implement the SionModel\Person\PersonProviderInterface
        */
        'person_provider' => SchoenstattTable::class,

        'route_permission_checking_enabled' => true,

        /**
         * Where the first occurrence of each distinct failure gets mailed.
         * Everything else about exception reporting (fingerprinting, the ignore
         * list, spike thresholds, capture profile) is documented on the defaults
         * in module/SionModel/config/module.config.php.
         *
         * A role address rather than an individual so reporting survives a
         * handover; make sure it lands in an inbox somebody reads.
         */
        'exception_notifications' => [
            'to'             => ['webmaster@schoenstatt.link'],
            'subject_prefix' => '[schoenstatt.link]',
        ],

        'persistent_cache_config' => [
            'adapter' => [
                'name' => 'apcu',
                'options' => [
                    'ttl' => 60 * 60 * 24 * 5, //5 days
                ],
            ],
        ],
    ],
];
