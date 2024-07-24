<?php

namespace OVAC\IDoc;

use Faker\Factory;
use Illuminate\Routing\Route;
use Mpociot\Reflection\DocBlock;
use Mpociot\Reflection\DocBlock\Tag;
use OVAC\IDoc\Tools\ResponseResolver;
use OVAC\IDoc\Tools\SchemaParser;
use OVAC\IDoc\Tools\Traits\ParamHelpers;
use ReflectionClass;
use ReflectionMethod;

class IDocGenerator
{
    use ParamHelpers;

    protected $schemaParser;

    public function __construct()
    {
        $this->schemaParser = new SchemaParser();
    }

    /**
     * Get the URI of the given route.
     *
     * @param Route $route
     * @return string
     */
    public function getUri(Route $route)
    {
        return $route->uri();
    }

    /**
     * Get the HTTP methods of the given route, excluding 'HEAD'.
     *
     * @param Route $route
     * @return array
     */
    public function getMethods(Route $route)
    {
        return array_diff($route->methods(), ['HEAD']);
    }

    /**
     * Process the given route to generate documentation.
     *
     * @param Route $route
     * @param array $rulesToApply Rules to apply when generating documentation for this route
     * @return array
     */
    public function processRoute(Route $route, array $rulesToApply = [])
    {
        $routeAction = $route->getAction();

        // Handle both old and new routing patterns
        if (is_string($routeAction['uses'])) {
            list($class, $method) = explode('@', $routeAction['uses']);
        } elseif (is_array($routeAction['uses']) && count($routeAction['uses']) === 2) {
            $class = $routeAction['uses'][0];
            $method = $routeAction['uses'][1];
        } else {
            throw new \InvalidArgumentException('Invalid route action format.');
        }

        $controller = new ReflectionClass($class);
        $method = $controller->getMethod($method);

        $routeGroup = $this->getRouteGroup($controller, $method);
        $docBlock = $this->parseDocBlock($method);
        $bodyParameters = $this->getBodyParametersFromDocBlock($docBlock['tags']);
        $queryParameters = $this->getQueryParametersFromDocBlock($docBlock['tags']);
        $pathParameters = $this->getPathParametersFromDocBlock($docBlock['tags']);
        $content = ResponseResolver::getResponse($route, $docBlock['tags'], [
            'rules' => $rulesToApply,
            'body' => $bodyParameters,
            'query' => $queryParameters,
        ]);

        // Extract schema documentation
        $schemas = $this->schemaParser->getSchemaDocumentation($docBlock['tags']);

        $parsedRoute = [
            'id' => md5($this->getUri($route) . ':' . implode($this->getMethods($route))),
            'group' => $routeGroup,
            'title' => $docBlock['short'],
            'description' => $docBlock['long'],
            'methods' => $this->getMethods($route),
            'uri' => $this->getUri($route),
            'bodyParameters' => $bodyParameters,
            'queryParameters' => $queryParameters,
            'pathParameters' => $pathParameters,
            'authenticated' => $authenticated = $this->getAuthStatusFromDocBlock($docBlock['tags']),
            'response' => $content,
            'showresponse' => !empty($content),
            'schemas' => $schemas,
        ];

        if (!$authenticated && array_key_exists('Authorization', ($rulesToApply['headers'] ?? []))) {
            unset($rulesToApply['headers']['Authorization']);
        }

        $parsedRoute['headers'] = $rulesToApply['headers'] ?? [];

        return $parsedRoute;
    }

    /**
     * Extract path parameters from the given doc block tags.
     *
     * @param array $tags
     * @return array
     */
    protected function getPathParametersFromDocBlock(array $tags)
    {
        $parameters = collect($tags)
            ->filter(function ($tag) {
                return $tag->getName() === 'pathParam';
            })
            ->mapWithKeys(function ($tag) {
                preg_match('/(.+?)\s+(.+?)\s+(required\s+)?(.*)/', $tag->getContent(), $content);
                if (empty($content)) {
                    // this means only name and type were supplied
                    list($name, $type) = preg_split('/\s+/', $tag->getContent());
                    $required = false;
                    $description = '';
                } else {
                    list($_, $name, $type, $required, $description) = $content;
                    $description = trim($description);
                    if ($description == 'required' && empty(trim($required))) {
                        $required = $description;
                        $description = '';
                    }
                    $required = trim($required) == 'required' ? true : false;
                }

                $type = $this->normalizeParameterType($type);
                list($description, $example) = $this->parseDescription($description, $type);
                $value = is_null($example) ? $this->generateDummyValue($type) : $example;

                return [$name => compact('type', 'description', 'required', 'value')];
            })->toArray();

        return $parameters;
    }

    /**
     * Extract body parameters from the given doc block tags.
     *
     * @param array $tags
     * @return array
     */
    protected function getBodyParametersFromDocBlock(array $tags)
    {
        $parameters = collect($tags)
            ->filter(function ($tag) {
                return $tag instanceof Tag && $tag->getName() === 'bodyParam';
            })
            ->mapWithKeys(function ($tag) {
                preg_match('/(.+?)\s+(.+?)\s+(required\s+)?(.*)/', $tag->getContent(), $content);
                if (empty($content)) {
                    // this means only name and type were supplied
                    list($name, $type) = preg_split('/\s+/', $tag->getContent());
                    $required = false;
                    $description = '';
                } else {
                    list($_, $name, $type, $required, $description) = $content;
                    $description = trim($description);
                    if ($description == 'required' && empty(trim($required))) {
                        $required = $description;
                        $description = '';
                    }
                    $required = trim($required) == 'required' ? true : false;
                }

                $type = $this->normalizeParameterType($type);
                list($description, $example) = $this->parseDescription($description, $type);
                $value = is_null($example) ? $this->generateDummyValue($type) : $example;

                return [$name => compact('type', 'description', 'required', 'value')];
            })->toArray();

        return $parameters;
    }

    /**
     * Determine if the route requires authentication based on the doc block tags.
     *
     * @param array $tags
     * @return bool
     */
    protected function getAuthStatusFromDocBlock(array $tags)
    {
        $authTag = collect($tags)
            ->first(function ($tag) {
                return $tag instanceof Tag && strtolower($tag->getName()) === 'authenticated';
            });

        return (bool) $authTag;
    }

    /**
     * Get the route group from the controller or method doc block.
     *
     * @param ReflectionClass $controller
     * @param ReflectionMethod $method
     * @return string
     */
    protected function getRouteGroup(ReflectionClass $controller, ReflectionMethod $method)
    {
        // @group tag on the method overrides that on the controller
        $docBlockComment = $method->getDocComment();
        if ($docBlockComment) {
            $phpdoc = new DocBlock($docBlockComment);
            foreach ($phpdoc->getTags() as $tag) {
                if ($tag->getName() === 'group') {
                    return $tag->getContent();
                }
            }
        }

        $docBlockComment = $controller->getDocComment();
        if ($docBlockComment) {
            $phpdoc = new DocBlock($docBlockComment);
            foreach ($phpdoc->getTags() as $tag) {
                if ($tag->getName() === 'group') {
                    return $tag->getContent();
                }
            }
        }

        return 'general';
    }

    /**
     * Extract query parameters from the given doc block tags.
     *
     * @param array $tags
     * @return array
     */
    protected function getQueryParametersFromDocBlock(array $tags)
    {
        $parameters = collect($tags)
            ->filter(function ($tag) {
                return $tag instanceof Tag && $tag->getName() === 'queryParam';
            })
            ->mapWithKeys(function ($tag) {
                preg_match('/(.+?)\s+(.+?)\s+(required\s+)?(.*)/', $tag->getContent(), $content);
                if (empty($content)) {
                    // this means only name and type were supplied
                    list($name, $type) = preg_split('/\s+/', $tag->getContent());
                    $required = false;
                    $description = '';
                } else {
                    list($_, $name, $type, $required, $description) = $content;
                    $description = trim($description);
                    if ($description == 'required' && empty(trim($required))) {
                        $required = $description;
                        $description = '';
                    }
                    $required = trim($required) == 'required' ? true : false;
                }

                $type = $this->normalizeParameterType($type);
                list($description, $example) = $this->parseDescription($description, $type);
                $value = is_null($example) ? $this->generateDummyValue($type) : $example;

                return [$name => compact('type', 'description', 'required', 'value')];
            })->toArray();

        return $parameters;
    }

    /**
     * Parse the doc block of the given method.
     *
     * @param ReflectionMethod $method
     * @return array
     */
    protected function parseDocBlock(ReflectionMethod $method)
    {
        $comment = $method->getDocComment();
        $phpdoc = new DocBlock($comment);

        return [
            'short' => $phpdoc->getShortDescription(),
            'long' => $phpdoc->getLongDescription()->getContents(),
            'tags' => $phpdoc->getTags(),
        ];
    }

    /**
     * Normalize the parameter type to a standard format.
     *
     * @param string $type
     * @return string
     */
    private function normalizeParameterType($type)
    {
        $typeMap = [
            'int' => 'integer',
            'bool' => 'boolean',
            'double' => 'float',
        ];

        return $type ? ($typeMap[$type] ?? $type) : 'string';
    }

    /**
     * Generate a dummy value for the given parameter type.
     *
     * @param string $type
     * @return mixed
     */
    private function generateDummyValue(string $type)
    {
        $faker = Factory::create();
        $fakes = [
            'integer' => function () {
                return rand(1, 20);
            },
            'number' => function () use ($faker) {
                return $faker->randomFloat();
            },
            'float' => function () use ($faker) {
                return $faker->randomFloat();
            },
            'boolean' => function () use ($faker) {
                return $faker->boolean();
            },
            'string' => function () use ($faker) {
                return $faker->asciify('************');
            },
            'array' => function () {
                return '[]';
            },
            'object' => function () {
                return '{}';
            },
            'json' => function () {
                return '{}';
            },
        ];

        $fake = $fakes[$type] ?? $fakes['string'];

        return $fake();
    }

    /**
     * Parse the description to extract the example value.
     *
     * @param string $description
     * @param string $type The type of the parameter. Used to cast the example provided, if any.
     * @return array The description and included example.
     */
    private function parseDescription(string $description, string $type)
    {
        $example = null;
        if (preg_match('/(.*)\s+Example:\s*(.*)\s*/', $description, $content)) {
            $description = $content[1];

            // examples are parsed as strings by default, we need to cast them properly
            $example = $this->castToType($content[2], $type);
        }

        return [$description, $example];
    }

    /**
     * Cast a value from a string to a specified type.
     *
     * @param string $value
     * @param string $type
     * @return mixed
     */
    private function castToType(string $value, string $type)
    {
        $casts = [
            'integer' => 'intval',
            'number' => 'floatval',
            'float' => 'floatval',
            'boolean' => 'boolval',
        ];

        // First, we handle booleans. We can't use a regular cast,
        // because PHP considers string 'false' as true.
        if ($value == 'false' && $type == 'boolean') {
            return false;
        }

        if (isset($casts[$type])) {
            return $casts[$type]($value);
        }

        return $value;
    }
}