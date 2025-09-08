<?php

return [
    /*
    |--------------------------------------------------------------------------
    | iDoc Domain
    |--------------------------------------------------------------------------
    |
    | This is the subdomain where the documentation will be accessible from.
    | If the setting is null, iDoc will reside under the same domain as the
    | application. otherwise, this value will be used as the subdomain.
    |
     */

    'domain' => null,

    /*
    |--------------------------------------------------------------------------
    | iDoc Path
    |--------------------------------------------------------------------------
    |
    | This is the URI path where the documentation will be accessible from.
    | Feel free to change this path to anything you like.
    |
     */

    'path' => 'idoc',

    /*
    |--------------------------------------------------------------------------
    | iDoc Route Middleware
    |--------------------------------------------------------------------------
    |
    | These middleware will be assigned to the iDoc route, giving you
    | the chance to add your own or change the idoc middleware.
    |
     */

    'middleware' => [
        'web',
    ],

    // Remove specific middleware from the iDoc group (docs + chat group).
    // Accepts a single alias/FQCN or an array. Base-name removal is supported
    // (eg. 'throttle' removes 'throttle:60,1').
    'remove_middleware' => env('IDOC_REMOVE_MIDDLEWARE', []),

    /*
    |--------------------------------------------------------------------------
    | iDoc Try It Out
    |--------------------------------------------------------------------------
    |
    | This option enables the "Try it out" feature on the documentation,
    | allowing users to make API calls directly from the documentation interface.
    | You can enable or disable this feature by setting the value to true or false.
    |
     */
    'tryit' => [
        'enabled' => env('IDOC_TRYIT_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | iDoc AI Chat assistant (optional)
    |--------------------------------------------------------------------------
    |
    | Enable a simple AI assistant that can answer questions about your API
    | using your generated OpenAPI spec and optional extra context from a view.
    |
    | Provider/API keys
    | -----------------
    | This feature is provider‑agnostic. Set `IDOC_CHAT_PROVIDER` and the
    | corresponding API key for your provider:
    |   - deepseek  → DEEPSEEK_API_KEY
    |   - openai    → OPENAI_API_KEY
    |   - google    → GOOGLE_API_KEY (or GEMINI_API_KEY)
    |   - groq      → GROQ_API_KEY
    |   - huggingface → HF_API_TOKEN (or HUGGINGFACE_API_KEY)
    |   - together  → TOGETHER_API_KEY (with base_url set to Together’s endpoint)
    |
    | You may also provide `IDOC_CHAT_API_KEY_ENV` to point to a custom env var.
    | Disable the feature entirely by setting `IDOC_CHAT_ENABLED=false`.
    */

    'chat' => [
        'enabled'   => env('IDOC_CHAT_ENABLED', true),

        // Model name for the chosen provider (override in .env)
        // Examples: deepseek-chat, gpt-4o-mini, gemini-1.5-flash,
        //           mixtral-8x7b-32768, Qwen/Qwen2.5-7B-Instruct
        'model'     => env('IDOC_CHAT_MODEL', 'deepseek-chat'),

        // Optional: the view used as extra context for chat (rendered to text)
        'info_view' => env('IDOC_CHAT_INFO_VIEW'), // eg. 'idoc.info'

        // Provider and endpoint configuration
        // Supported providers:
        // - 'deepseek'        → DeepSeek OpenAI‑compatible endpoint (default)
        // - 'openai'          → OpenAI ChatCompletions
        // - 'google'          → Google Gemini (GenerateContent API)
        // - 'groq'            → Groq OpenAI‑compatible endpoint
        // - 'huggingface'     → Hugging Face Inference API (serverless)
        // - 'together'        → Together AI (OpenAI‑compatible)
        // - 'openai_compat'   → Any OpenAI‑compatible server (LM Studio, llama.cpp)
        'provider'  => env('IDOC_CHAT_PROVIDER', 'deepseek'),

        // Base URL override (OpenAI‑compatible servers, Groq, Together, local, etc.)
        'base_url'  => env('IDOC_CHAT_BASE_URL'),

        // Env var to read API key from; if null, sensible defaults are used per provider
        // e.g. 'OPENAI_API_KEY', 'GROQ_API_KEY', 'HF_API_TOKEN', 'GOOGLE_API_KEY'
        'api_key_env' => env('IDOC_CHAT_API_KEY'),

        // Optional: absolute path to a Markdown file that contains the default
        // system prompt for the AI assistant. If null, iDoc will use the
        // package's bundled prompt at resources/prompts/chat-system.md.
        // Example env override:
        //   IDOC_CHAT_SYSTEM_PROMPT=/absolute/path/to/chat-system.md
        'system_prompt_md' => env('IDOC_CHAT_SYSTEM_PROMPT'),

        // Route customization
        // - route: the full named route used by the frontend to POST messages
        //          Defaults to 'idoc.chat'. You can change this if you want a
        //          different name (eg. 'docs.chat').
        // - uri:   the URI segment appended under the iDoc prefix where the
        //          endpoint will be exposed (eg. /{idoc.path}/chat).
        'route' => env('IDOC_CHAT_ROUTE', 'idoc.chat'),
        'uri'   => env('IDOC_CHAT_URI', 'chat'),

        // Extra middleware for chat only (string or array).
        // Default is stateless: throttle only. If a middleware you attach to
        // iDoc needs session, either remove it from chat via
        // 'chat.remove_middleware' or add the session trio here
        // (EncryptCookies, AddQueuedCookiesToResponse, StartSession).
        'middleware' => env('IDOC_CHAT_MIDDLEWARE', 'throttle:60,1'),

        // Middleware to remove for chat only (after global removals). String or
        // array. Use 'web' (default) for stateless chat, or remove specifics
        // like VerifyCsrfToken.
        'remove_middleware' => env('IDOC_CHAT_REMOVE_MIDDLEWARE', 'web'),
    ],

    /*
    |--------------------------------------------------------------------------
    | iDoc logo url
    |--------------------------------------------------------------------------
    |
    | This is the logo configuration for the documentation. The logo expects
    | an absolute or relative url to a logo image while the color will
    | fill any space left depending on the log size.
    |
     */

    'logo' => 'https://res.cloudinary.com/ovac/image/upload/h_300,w_380,c_fill,r_30,bo_20px_solid_white/aboust_ey5v1v.jpg',

    'color' => '',

    /*
    |--------------------------------------------------------------------------
    | iDoc principal information
    |--------------------------------------------------------------------------
    |
    | This is the principal information  that will be visible on the  documentation like
    | title, description , version, license, etc.
    |
     */

    'title' => 'iDoc API Reference',

    'description' => 'iDoc API specification and documentation.',

    'version' => '',

    'terms_of_service' => '',

    'contact' => [
        // 'name' => 'YOUR_NAME',
        // 'email' => 'YOUR_EMAIL',
        // 'url' => 'YOUR_URL'
    ],

    'license' => [
        // 'name' => 'YOUR_LICENSE',
        // 'url' => 'YOUR_URL_LICENSE'
    ],

    /*
    |--------------------------------------------------------------------------
    | iDoc collection/output path
    |--------------------------------------------------------------------------
    |
    | The output path for the generated Open API 3.0 collection file.
    | This path is relative to the public path.
    |
    | In order To disable the open-api-3 download button
    | on the  documentation, the `hide_download_button`
    | option should be set to true.
    |
     */

    'output' => '',

    'hide_download_button' => false,

    /*
    |--------------------------------------------------------------------------
    | iDoc external description
    |--------------------------------------------------------------------------
    |
    | This is the external description/info for the documentation. By default
    | it uses the 'idoc.info' route in the documentation, but you can
    | override it with your own route name or leave it empty to use the
    | default description. It must be a route name.
    |
    | For best results, format the file using Markdown.
    |
    | examples:
    | -  Using route name:
    |   'external_description' => 'idoc.info'
    |
    | -  You can also leave empty to use default description
    */

    'external_description' => 'idoc.info',

    /*
    |--------------------------------------------------------------------------
    | iDoc router
    |--------------------------------------------------------------------------
    |
    | The application's router.  (Laravel or Dingo).
    |
     */

    'router' => 'laravel',

    /*
    |--------------------------------------------------------------------------
    | iDoc servers
    |--------------------------------------------------------------------------
    |
    | The servers that should be added to the documentation. Each should have
    | a server hostname (and path if necessary) and a description of the
    | host. eg: one for test and another for production.
    |
     */

    'servers' => [
        [
            'url' => config('app.url'),
            'description' => 'Documentation generator server.',
        ],
        [
            'url' => 'http://test.example.com',
            'description' => 'Test server.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | iDoc tag groups
    |--------------------------------------------------------------------------
    |
    | This is used to separate groups in sections in the side menu.
    | Before you use it, make sure you add all tags to a group, since a tag that is not in a group,
    | will not be displayed at all!
    |
     */

    'tag_groups' => [
        // ["name"=> "Accounts", "tags"=>["Authentication", "Users"]]
    ],

    /*
    |--------------------------------------------------------------------------
    | iDoc languages tab.
    |--------------------------------------------------------------------------
    | Each tab is used to generate a request template for a given language.
    | New languages can be added and the existing ones modified after.
    |
    | You can add or edit new languages tabs by publishing the view files
    | and editing them or adding custom view files to:
    |
    |    'resources/views/vendor/idoc/languages/*.blade.php',
    |
    | where * is the name of the language you wish to add.
    |
    | Don't forget to add here too when done.
    |
     */

    'language-tabs' => [
        'bash' => 'Bash',
        'javascript' => 'Javascript',
        'php' => 'PHP',
    ],

    /*
    |--------------------------------------------------------------------------
    | iDoc security
    |--------------------------------------------------------------------------
    |
    | Here you can define the authentication and authorization schemes that your API use.
    | You just need to use the OpenAPI security definitions or simply set as null.
    |
    |
     */

    'security' => [
        'BearerAuth' => [
            'type' => 'http',
            'scheme' => 'bearer',
            'bearerFormat' => 'JWT',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | iDoc routes: The routes for which documentation should be generated.
    |--------------------------------------------------------------------------
    |
    | Each group contains rules defining which routes should be included
    | ('match', 'include' and 'exclude' sections) and rules which
    | should be applied to them ('apply' section).
    |
     */

    'routes' => [
        [
            /*
             * Specify conditions to determine what routes will be parsed in this group.
             * A route must fulfill ALL conditions to pass.
             */
            'match' => [

                /*
                 * Match only routes whose domains match this pattern (use * as a wildcard to match any characters).
                 */
                'domains' => [
                    '*',
                    // 'domain1.*',
                ],

                /*
                 * Match only routes whose paths match this pattern (use * as a wildcard to match any characters).
                 */
                'prefixes' => [
                    'api/*',
                ],

                /*
                 * Match only routes registered under this version. This option is ignored for Laravel router.
                 * Note that wildcards are not supported.
                 */
                'versions' => [
                    'v1',
                ],
            ],

            /*
             * Include these routes when generating documentation,
             * even if they did not match the rules above.
             * Note that the route must be referenced by name here (wildcards are supported).
             */
            'include' => [
                // 'users.index', 'healthcheck*'
            ],

            /*
             * Exclude these routes when generating documentation,
             * even if they matched the rules above.
             * Note that the route must be referenced by name here (wildcards are supported).
             */
            'exclude' => [
                // 'users.create', 'admin.*'
            ],

            /*
             * Specify rules to be applied to all the routes in this group when generating documentation
             */
            'apply' => [
                /*
                 * Specify headers to be added to the example requests
                 */
                'headers' => [
                    'Authorization' => 'Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJod',
                    // 'Api-Version' => 'v2',
                ],

                /*
                 * If no @response or @transformer declaratons are found for the route,
                 * we'll try to get a sample response by attempting an API call.
                 * Configure the settings for the API call here,
                 */
                'response_calls' => [
                    /*
                     * API calls will be made only for routes in this group matching these HTTP methods (GET, POST, etc).
                     * List the methods here or use '*' to mean all methods. Leave empty to disable API calls.
                     */
                    'methods' => ['*'],

                    /*
                     * For URLs which have parameters (/users/{user}, /orders/{id?}),
                     * specify what values the parameters should be replaced with.
                     * Note that you must specify the full parameter, including curly brackets and question marks if any.
                     */
                    'bindings' => [
                        // '{user}' => 1
                    ],

                    /*
                     * Environment variables which should be set for the API call.
                     * This is a good place to ensure that notifications, emails
                     * and other external services are not triggered during the documentation API calls
                     */
                    'env' => [
                        'APP_ENV' => 'documentation',
                        'APP_DEBUG' => false,
                        // 'env_var' => 'value',
                    ],

                    /*
                     * Headers which should be sent with the API call.
                     */
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                        // 'key' => 'value',
                    ],

                    /*
                     * Query parameters which should be sent with the API call.
                     */
                    'query' => [
                        // 'key' => 'value',
                    ],

                    /*
                     * Body parameters which should be sent with the API call.
                     */
                    'body' => [
                        // 'key' => 'value',
                    ],

                    /*
                     * Disable middlewares for API Call.
                     */
                    'without_middleware' => [
                        // \App\Http\Middleware\Authenticate::class
                    ]
                ],
            ],
        ],
    ],
];
