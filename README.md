<p align="center" style="border: 2px dotted #000000">
    <a href="https://https://www.openapis.org/" target="_blank"><img src="https://res.cloudinary.com/ovac/image/upload/h_50/v1553976486/1_Qf94xFwde421J_ZSmdPJDw_soee3m.png" class="inline"></a>
    <br/>
<a href="https://https://www.openapis.org/" target="_blank"><img src="https://res.cloudinary.com/ovac/image/upload/h_50/v1553975330/C2I2EFN2_400x400_qcuyop.jpg" class="inline"></a><a href="#" target="_blank"><img src="https://res.cloudinary.com/ovac/image/upload/h_120/v1506828786/logo-composer-transparent_zjgal0.png" class="inline"></a><a href="#" target="_blank"><img src="https://res.cloudinary.com/ovac/image/upload/h_50,w_60,c_fill/v1506832992/laravel-logo_atlvfw.png" class="inline"></a>
    <br/>
    <a href="https://www.ovac4u.com/idoc" target="_blank"><img src="https://res.cloudinary.com/ovac/image/upload/r_200,w_300,h_50,c_fill/v1506828380/logo_size_invert_jelh74.jpg" class="inline"></a>
</p>

<p align="center">
<a href="https://packagist.org/packages/ovac/idoc"><img src="https://poser.pugx.org/ovac/idoc/v/stable" alt="Latest Stable Version" class="inline"></a>
<a href="https://packagist.org/packages/ovac/idoc"><img src="https://poser.pugx.org/ovac/idoc/downloads" alt="Total Downloads" class="inline"></a>
<a href="https://packagist.org/packages/ovac/idoc"><img src="https://poser.pugx.org/ovac/idoc/license" alt="License" class="inline"></a>
</p>

```md
              Follow me anywhere @ovac4u                         | GitHub
              _________                          _________       | Twitter
             |   ___   |.-----.--.--.---.-.----.|  |  |.--.--.   | Facboook
             |  |  _   ||  _  |  |  |  _  |  __||__    |  |  |   | Instagram
             |  |______||_____|\___/|___._|____|   |__||_____|   | Github + @ovac
             |_________|                        www.ovac4u.com   | Facebook + @ovacposts
```

<br/>
<br/>

<div align="center">
    
<h2>Laravel IDoc - The API Documentation Generator</h2>

<p>Automatically generate an interactive API documentation from your existing Laravel routes. Take a look at the <a target="_blank" href="https://redocly.github.io/redoc/">example documentation</a>. Inspired by <a href="https://github.com/mpociot/laravel-apidoc-generator">Laravel Api Documentation Generator</a></p>
</div>

<br/>
<br/>


# Introduction.

Laravel IDoc generator (interactive documentation generator) is a seamless and complete plugin for generating API documentation from your Laravel's codebase. It is inspired by the laravel-apidoc-generator, ReDoc and the Open API initiative from Swagger. IDoc has been built with extendability so that it can easily adapt with your use case.


![Demo](https://raw.githubusercontent.com/Rebilly/ReDoc/master/demo/redoc-demo.png)

## Features
- Extremely easy deployment
- Server Side Rendering ready
- The widest OpenAPI v2.0 features support <br>
![](https://raw.githubusercontent.com/Rebilly/ReDoc/master/docs/images/discriminator-demo.gif){.inline}
- OpenAPI 3.0 support
- Neat **interactive** documentation for nested objects <br>
![](https://raw.githubusercontent.com/Rebilly/ReDoc/master/docs/images/nested-demo.gifdocs/images/nested-demo.gif){.inline}
- Automatic code sample support <br>
![](https://raw.githubusercontent.com/Rebilly/ReDoc/master/docs/images/code-samples-demo.gif){.inline}
- Responsive three-panel design with menu/scrolling synchronization
- Integrate API Introduction into side menu.
- High-level grouping in side-menu.
- Branding/customizations.


## Installation
> Note: PHP 7 and Laravel 5.5 or higher are the minimum dependencies.

```sh
$ composer require ovac/idoc
```

### Laravel
Publish the config file by running:

```bash
php artisan vendor:publish --tag=idoc-config
```
This will create an `idoc.php` file in your `config` folder.

### Lumen
- Register the service provider in your `bootstrap/app.php`:
```php
$app->bind('path.public', function ($app) { return $app->basePath('../your-public-path'); });
$app->register(\OVAC\IDoc\IDocLumenServiceProvider::class);
```
- Copy the config file from `vendor/ovac/idoc/config/idoc.php` to your project as `config/idoc.php`. Then add to your `bootstrap/app.php`:
```php
$app->configure('idoc');
```

## Usage
```bash 
$ php artisan idoc:generate
```

## Configuration
Before you can generate your documentation, you'll need to configure a few things in your `config/idoc.php`.

 - `path`
This will be used to register the necessary routes for the package.
 ```php
 'path' => 'idoc',
 ```

- `logo`
You can specify your custom logo to be used on the generated documentation. A relative or absolute url to the logo image.
```php
'logo' => 'https://res.cloudinary.com/ovac/image/upload/h_300,w_380,c_fill,r_30,bo_20px_solid_white/aboust_ey5v1v.jpg',
```

- `title`
Here, you can specify the title to place on the documentation page.
```php
'title' => 'iDoc API Reference',
 ```
 
- `description`
This will place a description on top of the documentation.
```php
'description' => 'iDoc Api secification and documentation.',
 ```

- `version`
Documentation version number.

- `terms_of_service`
This is the url to the terms and conditions for use your API.

- `contact`
Here you can configure contact information for support.
```php
'contact' => [
        'name' => 'API Support',
        'email' => 'iamovac@gmail.com',
        'url' => 'http://www.ovac4u.com'
],
 ```

- `license`
A short and simple permissive license with conditions only requiring preservation of copyright and license notices
```php
'license' => [
        'name' => 'MIT',
        'url' => 'https://github.com/ovac/idoc/blob/master/LICENSE.md'
],
 ```

- `output`
This package can automatically generate an Open-API 3.0 specification file for your routes, along with the documentation. This is the file path where the generated documentation will be written to. Default: **public/docs** 

 - `hide_download_button`
This section is where you can configure if you want a download button visible on the documentation.

- `router`
The router to use when processing the route (can be Laravel or Dingo. Defaults to **Laravel**)

- `servers`
The servers array can be used to add multiple endpoints on the documentation so that the user can switch between endpoints. For example, This could be a test server and the live server.
 ```php
'servers' => [
    [
        'url' => 'https://www.ovac4u.com',
        'description' => 'App live server.',
    ],
    [
        'url' => 'https://test.ovac4u.com',
        'description' => 'App test server.',
    ],
],
 ```

- `tag_groups`
This array is used to separate groups that you have defined in little sections in the side menu. If you want to use it, make sure you add all groups because the unadded group will not be displayed.

- `external_description`
This option allows you to specify a route name for an external description that will be used in the documentation. If not provided, it will default to the route `idoc.info`. By default, it is set to `idoc.info`.

```php
'external_description' => 'idoc.info', // Route name for external description, leave empty to use default description
```

Example usage:
```php
'external_description' => 'idoc.info',
```

- `language-tabs`
This is where you can set languages used to write request samples. Each item in array is used to generate a request template for a given language. New languages can be added and the existing ones modified after. You can add or edit new languages tabs by publishing the view files and editing them or adding custom view files to:
 ```php
'resources/views/vendor/idoc/languages/LANGUAGE.blade.php',
 ```

- `security`
This is where you specify authentication and authorization schemes, by default the HTTP authentication scheme using Bearer is setting but you can modify it, add others or even define it as null according to the requirements of your project. For more information, please visit [Swagger Authentication](https://swagger.io/docs/specification/authentication/).

 ```php
 'security' => [
        'BearerAuth' => [
            'type' => 'http',
            'scheme' => 'bearer',
            'bearerFormat' => 'JWT',
        ],
    ],
 ```

- `routes`
This is where you specify what rules documentation should be generated for. You specify routes to be parsed by defining conditions that the routes should meet and rules that should be applied when generating documentation. These conditions and rules are specified in groups, allowing you to apply different rules to different routes.

For instance, suppose your configuration looks like this:

```php
return [
     //...,
  
     /*
     * The routes for which documentation should be generated.
     * Each group contains rules defining what routes should be included ('match', 'include' and 'exclude' sections)
     * and rules which should be applied to them ('apply' section).
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

            //...
        ],
    ],
```

This means documentation will be generated for routes in all domains ('&ast;' is a wildcard meaning 'any character') which match any of the patterns 'api/&ast;' or 'v2-api/&ast;', excluding the 'users.create' route and any routes whose names begin with `admin.`, and including the 'users.index' route and any routes whose names begin with `healthcheck.`. (The `versions` key is ignored unless you are using Dingo router).
Also, in the generated documentation, these routes will have the header 'Authorization: Bearer: {token}' added to the example requests.

You can also separate routes into groups to apply different rules to them:

```php
<?php
return [
     //...,
  
     'routes' => [
          [
              'match' => [
                  'domains' => ['v1.*'],
                  'prefixes' => ['*'],
              ],
              'include' => [],
              'exclude' => [],
              'apply' => [
                  'headers' => [
                      'Token' => '{token}',
                      'Version' => 'v1',
                  ],
              ],
          ],
          [
              'match' => [
                  'domains' => ['v2.*'],
                  'prefixes' => ['*'],
              ],
              'include' => [],
              'exclude' => [],
              'apply' => [
                  'headers' => [
                      'Authorization' => 'Bearer: {token}',
                      'Api-Version' => 'v2',
                  ],
              ],
          ],
];
```

With the configuration above, routes on the `v1.*` domain will have the `Token` and `Version` headers applied, while routes on the `v2.*` domain will have the `Authorization` and `Api-Version` headers applied.

> Note: the `include` and `exclude` items are arrays of route names. THe &ast; wildcard is supported.
> Note: If you're using DIngo router, the `versions` parameter is required in each route group. This parameter does not support wildcards. Each version must be listed explicitly,

To generate your API documentation, use the `idoc:generate` artisan command.

```sh
$ php artisan idoc:generate

```

It will generate documentation using your specified configuration.

## Documenting your API

This package uses these resources to generate the API documentation:

### Grouping endpoints

This package uses the HTTP controller doc blocks to create a table of contents and show descriptions for your API methods.

Using `@group` in a controller doc block creates a Group within the API documentation. All routes handled by that controller will be grouped under this group in the sidebar. The short description after the `@group` should be unique to allow anchor tags to navigate to this section. A longer description can be included below. Custom formatting and `<aside>` tags are also supported. (see the [Documentarian docs](http://marcelpociot.de/documentarian/installation/markdown_syntax))

 > Note: using `@group` is optional. Ungrouped routes will be placed in a "general" group.

Above each method within the controller you wish to include in your API documentation you should have a doc block. This should include a unique short description as the first entry. An optional second entry can be added with further information. Both descriptions will appear in the API documentation in a different format as shown below.
You can also specify an `@group` on a single method to override the group defined at the controller level.

```php
/**
 * @group User management
 *
 * APIs for managing users
 */
class UserController extends Controller
{

	/**
	 * Create a user
	 *
	 * [Insert optional longer description of the API endpoint here.]
	 *
	 */
	 public function createUser()
	 {

	 }
	 
	/**
	 * @group Account management
	 *
	 */
	 public function changePassword()
	 {

	 }
}
```

### Specifying request parameters

To specify a list of valid parameters your API route accepts, use the `@bodyParam`, `@queryParam` and `@pathParam` annotations.
- The `@bodyParam` annotation takes the name of the parameter, its type, an optional "required" label, and then its description. 
- The `@queryParam` annotation takes the name of the parameter, an optional "required" label, and then its description
- The `@pathParam` annotation takes the name of the parameter, an optional "required" label, and then its description


```php

/**
 * @group Items
 */
class ItemController extends Controller
{

    /**
     * List items
     *
     * Get a list of items.
     *
     * @authenticated
     * @responseFile responses/items.index.json
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //...
    }

    /**
     * Store item
     *
     * Add a new item to the items collection.
     *
     * @bodyParam name string required
     * The name of the item. Example: Samsung Galaxy s10
     *
     * @bodyParam price number required
     * The price of the item. Example: 100.00
     *
     * @authenticated
     * @response {
     *      "status": 200,
     *      "success": true,
     *      "data": {
     *          "id": 10,
     *          "price": 100.00,
     *          "name": "Samsung Galaxy s10"
     *      }
     * }
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //...
    }


    /**
     * Get item
     *
     * Get item by it's unique ID.
     *
     * @pathParam item integer required
     * The ID of the item to retrieve. Example: 10
     *
     * @response {
     *      "status": 200,
     *      "success": true,
     *      "data": {
     *          "id": 10,
     *          "price": 100.00,
     *          "name": "Samsung Galaxy s10"
     *      }
     * }
     * @authenticated
     *
     * @param  \App\Item  $item
     * @return \Illuminate\Http\Response
     */
    public function show(Item $item)
    {
        //...
    }
```

They will be included in the generated documentation text and example requests.

**Result:** 
![Result](https://res.cloudinary.com/ovac/image/upload/v1556662864/shadow_image_103039_ssqirj.png)

Note: a random value will be used as the value of each parameter in the example requests. If you'd like to specify an example value, you can do so by adding `Example: your-example` to the end of your description. For instance:

```php
    /**
     * @pathParam location_id required The id of the location.
     * @queryParam user_id required The id of the user. Example: me
     * @queryParam page required The page number. Example: 4
     * @bodyParam user_id int required The id of the user. Example: 9
     * @bodyParam room_id string The id of the room.
     * @bodyParam forever boolean Whether to ban the user forever. Example: false
     */
```

Note: You can also add the `@bodyParam` annotations to a `\Illuminate\Foundation\Http\FormRequest` subclass:

```php
/**
 * @bodyParam title string required The title of the post.
 * @bodyParam body string required The title of the post.
 * @bodyParam type string The type of post to create. Defaults to 'textophonious'.
 * @bodyParam author_id int the ID of the author
 * @bodyParam thumbnail image This is required if the post type is 'imagelicious'.
 */
class MyRequest extends \Illuminate\Foundation\Http\FormRequest
{

}

public function createPost(MyRequest $request)
{
    // ...
}
```

### Indicating auth status
You can use the `@authenticated` annotation on a method to indicate if the endpoint is authenticated. A field for authentication token will be made available and marked as required on the interractive documentation.

### Providing an example response
You can provide an example response for a route. This will be displayed in the examples section. There are several ways of doing this.

#### @response
You can provide an example response for a route by using the `@response` annotation with valid JSON:

```php
/**
 * @response {
 *  "id": 4,
 *  "name": "Jessica Jones",
 *  "roles": ["admin"]
 * }
 */
public function show($id)
{
    return User::find($id);
}
```

Moreover, you can define multiple `@response` tags as well as the HTTP status code related to a particular response (if no status code set, `200` will be returned):
```php
/**
 * @response {
 *  "id": 4,
 *  "name": "Jessica Jones",
 *  "roles": ["admin"]
 * }
 * @response 404 {
 *  "message": "No query results for model [\App\User]"
 * }
 */
public function show($id)
{
    return User::findOrFail($id);
}
```

#### @transformer, @transformerCollection, and @transformerModel
You can define the transformer that is used for the result of the route using the `@transformer` tag (or `@transformerCollection` if the route returns a list). The package will attempt to generate an instance of the model to be transformed using the following steps, stopping at the first successful one:

1. Check if there is a `@transformerModel` tag to define the model being transformed. If there is none, use the class of the first parameter to the transformer's `transform()` method.
2. Get an instance of the model from the Eloquent model factory
2. If the parameter is an Eloquent model, load the first from the database.
3. Create an instance using `new`.

Finally, it will pass in the model to the transformer and display the result of that as the example response.

For example:

```php
/**
 * @transformercollection \App\Transformers\UserTransformer
 * @transformerModel \App\User
 */
public function listUsers()
{
    //...
}

/**
 * @transformer \App\Transformers\UserTransformer
 */
public function showUser(User $user)
{
    //...
}

/**
 * @transformer \App\Transformers\UserTransformer
 * @transformerModel \App\User
 */
public function showUser(int $id)
{
    // ...
}
```
For the first route above, this package will generate a set of two users then pass it through the transformer. For the last two, it will generate a single user and then pass it through the transformer.

> Note: for transformer support, you need to install the league/fractal package

```bash
composer require league/fractal
```

#### @responseFile

For large response bodies, you may want to use a dump of an actual response. You can put this response in a file (as a JSON string) within your Laravel storage directory and link to it. For instance, we can put this response in a file named `users.get.json` in `storage/responses`:

```
{"id":5,"name":"Jessica Jones","gender":"female"}
```

Then in your controller, link to it by:

```php
/**
 * @responseFile responses/users.get.json
 */
public function getUser(int $id)
{
  // ...
}
```
The package will parse this response and display in the examples for this route.

Similarly to `@response` tag, you can provide multiple `@responseFile` tags along with the HTTP status code of the response:
```php
/**
 * @responseFile responses/users.get.json
 * @responseFile 404 responses/model.not.found.json
 */
public function getUser(int $id)
{
  // ...
}
```

#### Generating responses automatically
If you don't specify an example response using any of the above means, this package will attempt to get a 

response by making a request to the route (a "response call"). A few things to note about response calls:
- They are done within a database transaction and changes are rolled back afterwards.
- The configuration for response calls is located in the `config/idoc.php`. They are configured within the `['apply']['response_calls']` section for each route group, allowing you to apply different settings for different sets of routes.
- By default, response calls are only made for GET routes, but you can configure this. Set the `methods` key to an array of methods or '*' to mean all methods. Leave it as an empty array to turn off response calls for that route group.
- Parameters in URLs (example: `/users/{user}`, `/orders/{id?}`) will be replaced with '1' by default. You can configure this, however. Put the parameter names (including curly braces and question marks) as the keys and their replacements as the values in the `bindings` key.
- You can configure environment variables (this is useful so you can prevent external services like notifications from being triggered). By default the APP_ENV is set to 'documentation'. You can add more variables in the `env` key.
- By default, the package will generate dummy values for your documented body and query parameters and send in the request. (If you specified example values using `@bodyParam` or `@queryParam`, those will be used instead.) You can configure what headers and additional query and parameters should be sent when making the request (the `headers`, `query`, and `body` keys respectively).
- By default all middlewares are enabled, but you can set the `without_middleware` array to specify the middlewares you prefer to disable, you can even use ['*'] to disable all.


### Open-API 3.0 spec file

The generator automatically creates an Open-API 3.0 spec file, which you can import to use within any external api application.

The default base URL added to the spec file will be that found in your Laravel `config/app.php` file. This will likely be `http://localhost`. If you wish to change this setting you can directly update the url or link this config value to your environment file to make it more flexible (as shown below):

```php
'url' => env('APP_URL', 'http://yourappdefault.app'),
```

If you are referring to the environment setting as shown above, then you should ensure that you have updated your `.env` file to set the APP_URL value as appropriate. Otherwise the default value (`http://yourappdefault.app`) will be used in your spec file. Example environment value:

```
APP_URL=http://yourapp.app
```

## Documenting Complex Responses with @responseResource

The `@responseResource` annotation allows you to easily document complex response structures using Laravel API Resources. This feature streamlines the process of generating comprehensive API documentation for nested and complex data structures, including automatic generation of example responses.

### Usage

To use the `@responseResource` annotation, add it to your controller method's PHPDoc block:

```php
/**
 * @responseResource App\Http\Resources\OrderResource
 */
public function show($id)
{
    return new OrderResource(Order::findOrFail($id));
}
```

You can also specify a status code:

```php
/**
 * @responseResource 201 App\Http\Resources\OrderResource
 */
public function store(Request $request)
{
    $order = Order::create($request->all());
    return new OrderResource($order);
}
```

### Documenting the Resource Class

In your API Resource class, use the following tags in the class-level DocBlock to provide metadata about the resource:

- `@resourceName`: Specifies a custom name for the resource in the documentation.
- `@resourceDescription`: Provides a description of the resource.
- `@resourceStatus`: Sets a default HTTP status code for the resource.

Example:

```php
/**
 * @resourceName Order
 * @resourceDescription Represents an order in the system
 * @resourceStatus 200
 */
class OrderResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            /**
             * @responseParam id integer required The ID of the order. Example: 1
             */
            'id' => $this->id,
            /**
             * @responseParam status string required The status of the order. Enum: [pending, processing, shipped, delivered]. Example: processing
             */
            'status' => $this->status,
            /**
             * @responseParam items array required The items in the order.
             */
            'items' => $this->items->map(function ($item) {
                return [
                    /**
                     * @responseParam id integer required The ID of the item. Example: 101
                     */
                    'id' => $item->id,
                    /**
                     * @responseParam name string required The name of the item. Example: Ergonomic Keyboard
                     */
                    'name' => $item->name,
                    /**
                     * @responseParam price float required The price of the item. Example: 129.99
                     */
                    'price' => $item->price,
                ];
            }),
        ];
    }
}
```

Use `@responseParam` annotations within the `toArray` method to document individual fields of the resource. You can specify the following for each field:

- Type (e.g., integer, string, array)
- Whether it's required
- Description
- Example value
- Enum values (if applicable)

The `@responseResource` annotation automatically parses your API Resource class to generate a detailed schema of your response structure, including nested relationships and complex data types. Additionally, it automatically generates an example response based on the provided example values or default values for each field type.

## Further modification

The info file in the view folder can be further modified to add introductions and further documentation.

## Using the Custom Configuration Generator

The `idoc:custom` command allows you to generate API documentation using a custom configuration file. This is useful when you need to generate documentation with different settings without modifying the default configuration.

### Command Signature

The command signature is:

```sh
php artisan idoc:custom {config?}
```


- `config` (optional): The name of the custom configuration file (without the `.php` extension) located in the `config` directory.

### How to Use

1. **Create a Custom Configuration File:**

   Create a custom configuration file in the `config` directory. The file should follow the naming convention `idoc.{config}.php`, where `{config}` is the name you will use when running the command.

   Example for `config/idoc.ecommerce.php`:
   ```
   // config/idoc.ecommerce.php
   return [
       'title' => 'E-commerce API Documentation',
       'version' => '1.0.0',
       'description' => 'API documentation for e-commerce.',
       'terms_of_service' => 'https://example.com/terms',
       'contact' => [
           'name' => 'E-commerce API Support',
           'email' => 'support@example.com',
           'url' => 'https://example.com',
       ],
       'license' => [
           'name' => 'MIT',
           'url' => 'https://opensource.org/licenses/MIT',
       ],
       'output' => '/docs/ecommerce', // Ensure this path is unique
       'hide_download_button' => false,
       'external_description' => route('ecommerce-doc-description'),
       'routes' => [
           [
               'match' => [
                   'domains' => ['*'],
                   'prefixes' => ['api/ecommerce/*'],
                   'versions' => ['v1'],
               ],
               'include' => [],
               'exclude' => [],
               'apply' => [
                   'headers' => [
                       'Authorization' => 'Bearer {token}',
                   ],
                   'response_calls' => [
                       'methods' => ['*'],
                       'bindings' => [],
                       'env' => [
                           'APP_ENV' => 'documentation',
                           'APP_DEBUG' => false,
                       ],
                       'headers' => [
                           'Content-Type' => 'application/json',
                           'Accept' => 'application/json',
                       ],
                       'query' => [],
                       'body' => [],
                       'without_middleware' => [],
                   ],
               ],
           ],
       ],
   ];
   ```

2. **Run the Command:**

   Run the command with the name of your custom configuration file (without the `.php` extension).

   Example:
   ```
   php artisan idoc:custom ecommerce
   ```

   If the custom configuration file exists, it will be loaded and merged with the default configuration. The command will then generate the API documentation using the merged configuration.

3. **Check the Output:**

   The generated documentation will be saved to the path specified in the `output` configuration option of your custom configuration file. Ensure that the output path is unique for each custom documentation to avoid conflicts. This is relative to the public directory.

   - E-commerce API documentation: `/docs/ecommerce`, will save the open-api spec file to `public/docs/ecommerce/openapi.json` and the documentation to `public/docs/ecommerce/index.html`.
   - User Management API documentation: `/docs/user` will save the open-api spec file to `public/docs/user/openapi.json` and the documentation to `public/docs/user/index.html`.

By using the custom configuration generator, you can easily manage and generate multiple sets of API documentation for different applications within the same Laravel application. This approach allows you to maintain separate configurations and documentation outputs for each API, ensuring clarity and organization.

### Managing Multiple API Documentation Sets

The custom configuration generator can also help you manage multiple sets of API documentation for different applications within the same Laravel application. This is particularly useful if you have different API sets for different applications or modules.

#### Example Scenario

Suppose you have a Laravel application that serves multiple APIs for different applications, such as a user management API, and an e-commerce API. You can create separate configuration files for each API and use the custom configuration generator to generate the documentation accordingly.

1. **Create Configuration Files:**

   - `config/idoc.ecommerce.php`
   - `config/idoc.user.php`

2. **Run the Command for Each API:**

   ```
   php artisan idoc:custom ecommerce
   php artisan idoc:custom user
   ```

   This will generate the API documentation for each application using the respective configuration file.

3. **Check the Output:**

   The generated documentation will be saved to the paths specified in the `output` configuration options of your custom configuration files. Ensure that each output path is unique to avoid conflicts. This is relative to the public directory.

   - E-commerce API documentation: `/docs/ecommerce`, will save the open-api spec file to `public/docs/ecommerce/openapi.json` and the documentation to `public/docs/ecommerce/index.html`.
   - User Management API documentation: `/docs/user` will save the open-api spec file to `public/docs/user/openapi.json` and the documentation to `public/docs/user/index.html`.

By using the custom configuration generator, you can easily manage and generate multiple sets of API documentation for different applications within the same Laravel application. This approach allows you to maintain separate configurations and documentation outputs for each API, ensuring clarity and organization.

### Defining Custom Documentation Routes

To serve the generated documentation for each custom configuration, you need to define routes in your `routes/web.php` or a similar routes file. This ensures that each set of documentation is accessible via a unique URL.

Example for `idoc.ecommerce.php` configuration:

```
1. **Create a Custom Configuration File:**

   Create a custom configuration file in the `config` directory. The file should follow the naming convention `idoc.{config}.php`, where `{config}` is the name you will use when running the command.

   Example for `config/idoc.ecommerce.php`:
   ```php
   // config/idoc.ecommerce.php
   return [
       'title' => 'E-commerce API Documentation',
       'version' => '1.0.0',
       'description' => 'API documentation for e-commerce.',
       'terms_of_service' => 'https://example.com/terms',
       'contact' => [
           'name' => 'E-commerce API Support',
           'email' => 'support@example.com',
           'url' => 'https://example.com',
       ],
       'license' => [
           'name' => 'MIT',
           'url' => 'https://opensource.org/licenses/MIT',
       ],
       'output' => '/docs/ecommerce', // Ensure this path is unique
       'hide_download_button' => false,
       'external_description' => route('ecommerce-doc-description'),
       'routes' => [
           [
               'match' => [
                   'domains' => ['*'],
                   'prefixes' => ['api/ecommerce/*'],
                   'versions' => ['v1'],
               ],
               'include' => [],
               'exclude' => [],
               'apply' => [
                   'headers' => [
                       'Authorization' => 'Bearer {token}',
                   ],
                   'response_calls' => [
                       'methods' => ['*'],
                       'bindings' => [],
                       'env' => [
                           'APP_ENV' => 'documentation',
                           'APP_DEBUG' => false,
                       ],
                       'headers' => [
                           'Content-Type' => 'application/json',
                           'Accept' => 'application/json',
                       ],
                       'query' => [],
                       'body' => [],
                       'without_middleware' => [],
                   ],
               ],
           ],
       ],
   ];
   ```

2. **Run the Command:**

   Run the command with the name of your custom configuration file (without the `.php` extension).

   Example:
   ```bash
   php artisan idoc:custom ecommerce
   ```

   If the custom configuration file exists, it will be loaded and merged with the default configuration. The command will then generate the API documentation using the merged configuration.

3. **Check the Output:**

   The generated documentation will be saved to the path specified in the `output` configuration option of your custom configuration file. Ensure that the output path is unique for each custom documentation to avoid conflicts.

### Managing Multiple API Documentation Sets

The custom configuration generator can also help you manage multiple sets of API documentation for different applications within the same Laravel application. This is particularly useful if you have different API sets for different applications or modules.

#### Example Scenario

Suppose you have a Laravel application that serves multiple APIs for different applications, such as a user management API, and an e-commerce API. You can create separate configuration files for each API and use the custom configuration generator to generate the documentation accordingly.

1. **Create Configuration Files:**

   - `config/idoc.ecommerce.php`
   - `config/idoc.user.php`

2. **Run the Command for Each API:**

   ```bash
   php artisan idoc:custom ecommerce
   php artisan idoc:custom user
   ```

   This will generate the API documentation for each application using the respective configuration file.

3. **Check the Output:**

   The generated documentation will be saved to the paths specified in the `output` configuration options of your custom configuration files. Ensure that each output path is unique to avoid conflicts. This is relative to the public directory.

   - E-commerce API documentation: `/docs/ecommerce`, will save the open-api spec file to `public/docs/ecommerce/openapi.json` and the documentation to `public/docs/ecommerce/index.html`.
   - User Management API documentation: `/docs/user` will save the open-api spec file to `public/docs/user/openapi.json` and the documentation to `public/docs/user/index.html`.

By using the custom configuration generator, you can easily manage and generate multiple sets of API documentation for different applications within the same Laravel application. This approach allows you to maintain separate configurations and documentation outputs for each API, ensuring clarity and organization.

### Defining Custom Documentation Routes

To serve the generated documentation for each custom configuration, you need to define routes in your `routes/web.php` or a similar routes file. This ensures that each set of documentation is accessible via a unique URL.

Example for `idoc.ecommerce.php` configuration:

```php
// routes/web.php

// Documentation for the ecommerce routes
Route::group([], function () {
    // Set the idoc config to the ecommerce config
    config(['idoc' => config('idoc.ecommerce')]);

    // Define the route for the user documentation
    Route::view(config('idoc.path'), 'idoc::documentation');
});
```

## Credits

This software uses the following open source packages:

- [Laravel](https://laravel.com/)
- [Redoc](https://github.com/Rebilly/ReDoc)
- [Ramsey UUID](https://github.com/ramsey/uuid)


## You may also like...

- [Laravel Api Documentation Generator](mpociot/laravel-apidoc-generator) - A laravel api documentation generator.

## License

MIT
