<?php

namespace OVAC\IDoc\Tools;

use ReflectionClass;
use SplFileObject;
use Mpociot\Reflection\DocBlock;
use Mpociot\Reflection\DocBlock\Tag;

class SchemaParser
{
    /**
     * Parse the schema from the given file object.
     *
     * This method reads through the lines of a file and extracts schema information
     * based on the @responseParam annotations found in the comments.
     *
     * @param SplFileObject $file The file object to parse.
     * @param int $endLine The line number to stop parsing at.
     * @return array The parsed schema as an associative array.
     */
    public function parseSchema(SplFileObject $file, int $endLine)
    {
        $schema = [];
        $currentField = null;
        $currentSchema = &$schema;
        $nestedSchemas = [];
        $multilineComment = '';

        while ($file->key() < $endLine) {
            $line = $file->current();
            $file->next();

            // Handle multiline comments
            if (preg_match('/\*\s+@responseParam\s+(.+?)\s+(.+?)\s+(required\s+)?(.*)/', $line, $matches) || preg_match('/\/\/\s+@responseParam\s+(.+?)\s+(.+?)\s+(required\s+)?(.*)/', $line, $matches)) {
                $multilineComment = $line;
                while ($file->key() < $endLine && !preg_match('/\*\//', $line)) {
                    $line = $file->current();
                    $file->next();
                    $multilineComment .= ' ' . trim($line);
                }
                $line = $multilineComment;
            }

            // Match @responseParam annotations
            if (preg_match('/\*\s+@responseParam\s+(.+?)\s+(.+?)\s+(required\s+)?(.*)/', $line, $matches)) {
                $name = $matches[1];
                $type = $matches[2];
                $required = !empty($matches[3]);
                $description = trim($matches[4], " */");

                // Extract example from description
                $example = null;
                if (preg_match('/Example:\s*(.*)/', $description, $exampleMatches)) {
                    $example = $exampleMatches[1];
                    $description = trim(str_replace($exampleMatches[0], '', $description));
                }

                // Extract enum values from description
                $enum = null;
                if (preg_match('/Enum:\s*\[(.*)\]/', $description, $enumMatches)) {
                    $enum = array_map('trim', explode(',', $enumMatches[1]));
                    $description = trim(str_replace($enumMatches[0], '', $description));
                }

                // Add the parsed field to the current schema
                $currentSchema[$name] = [
                    'type' => $type,
                    'description' => $description,
                    'required' => $required,
                    'example' => $example,
                    'enum' => $enum,
                ];

                // Handle nested schemas for array, object, or json types
                if ($type === 'array') {
                    $nestedSchemas[] = &$currentSchema;
                    $currentSchema[$name]['items'] = [];
                    $currentSchema = &$currentSchema[$name]['items'];
                } elseif (in_array($type, ['object', 'json'])) {
                    $nestedSchemas[] = &$currentSchema;
                    $currentSchema[$name]['properties'] = [];
                    $currentSchema = &$currentSchema[$name]['properties'];
                }
            } elseif (preg_match('/\s*\]\s*,?\s*$/', $line) || preg_match('/\s*\}\s*,?\s*$/', $line)) {
                // Handle the end of a nested schema
                if (!empty($nestedSchemas)) {
                    $currentSchema = &$nestedSchemas[count($nestedSchemas) - 1];
                    array_pop($nestedSchemas);
                }
            }
        }

        return $schema;
    }

    /**
     * Extract schema documentation from the given doc block tags.
     *
     * This method processes an array of doc block tags to extract schema documentation
     * for response resources.
     *
     * @param array $tags The array of doc block tags to process.
     * @return array The extracted schema documentation.
     */
    public function getSchemaDocumentation(array $tags)
    {
        $schemas = [];

        foreach ($tags as $tag) {
            if ($tag instanceof Tag && in_array($tag->getName(), ['responseResource'])) {
                $content = $tag->getContent();
                preg_match('/(\d+)?\s*(.*)/', $content, $matches);
                $statusCode = $matches[1];
                $resourceClass = $matches[2];

                if (!class_exists($resourceClass)) {
                    throw new \Exception(
                        "Error in @responseResource annotation: Class '{$resourceClass}' does not exist.\n\n" .
                        "Please ensure you've provided the fully qualified class name, including the namespace.\n" .
                        "Example: @responseResource App\\Http\\Resources\\UserResource\n\n" .
                        "If you're using a collection resource, make sure to use the correct class name.\n" .
                        "Example: @responseResource 200 App\\Http\\Resources\\UserCollection\n\n" .
                        "Check your controller method's PHPDoc block and verify the class name and namespace."
                    );
                }

                $reflectionClass = new ReflectionClass($resourceClass);
                $classDocBlock = $this->parseClassDocBlock($reflectionClass);
                $method = $reflectionClass->getMethod('toArray');
                $fileName = $reflectionClass->getFileName();
                $startLine = $method->getStartLine();
                $endLine = $method->getEndLine();

                $file = new SplFileObject($fileName);
                $file->seek($startLine - 1);

                $schema = $this->parseSchema($file, $endLine);

                $schemas[] = [
                    'name' => $classDocBlock['resourceName'] ?? $reflectionClass->getShortName(),
                    'statusCode' => $classDocBlock['resourceStatus'] ?? $statusCode ?: '200',
                    'description' => $classDocBlock['resourceDescription'] ?? '',
                    'properties' => $schema,
                    'example' => $this->generateExampleResponse($schema),
                ];
            }
        }

        return $schemas;
    }

    /**
     * Parse the class doc block to extract resource name, description, and status.
     *
     * This method reads the doc block of a given class and extracts the resource name,
     * description, and status from the @resourceName, @resourceDescription, and @resourceStatus
     * annotations.
     *
     * @param ReflectionClass $reflectionClass The reflection class to parse.
     * @return array An associative array containing the resource name, description, and status.
     */
    protected function parseClassDocBlock(ReflectionClass $reflectionClass)
    {
        $docBlock = $reflectionClass->getDocComment();
        $phpdoc = new DocBlock($docBlock);

        $resourceName = null;
        $resourceDescription = null;
        $resourceStatus = null;

        foreach ($phpdoc->getTags() as $tag) {
            if ($tag->getName() === 'resourceName') {
                $resourceName = $tag->getContent();
            } elseif ($tag->getName() === 'resourceDescription') {
                $resourceDescription = $tag->getContent();
            } elseif ($tag->getName() === 'resourceStatus') {
                $resourceStatus = $tag->getContent();
            }
        }

        return [
            'resourceName' => $resourceName,
            'resourceDescription' => $resourceDescription,
            'resourceStatus' => $resourceStatus,
        ];
    }

    /**
     * Generate an example response from the given schema.
     *
     * @param array $schema
     * @return array
     */
    protected function generateExampleResponse(array $schema)
    {
        $response = [];

        foreach ($schema as $name => $property) {
            if ($property['type'] === 'array') {
                $response[$name] = [$this->generateExampleResponse($property['items'] ?? [])];
            } elseif (in_array($property['type'], ['object', 'json'])) {
                $response[$name] = $this->generateExampleResponse($property['properties'] ?? []);
            } else {
                $response[$name] = $property['example'] ?? $this->generateDummyValue($property['type']);
            }
        }

        return $response;
    }

    /**
     * Generate a dummy value for the given parameter type.
     *
     * @param string $type
     * @return mixed
     */
    protected function generateDummyValue(string $type)
    {
        switch ($type) {
            case 'integer':
                return 1;
            case 'float':
            case 'double':
                return 1.0;
            case 'boolean':
                return true;
            case 'string':
                return 'example';
            case 'array':
                return [];
            case 'object':
            case 'json':
                return new \stdClass();
            default:
                return null;
        }
    }
}