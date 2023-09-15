<?php

namespace Hypnodev\OpenapiGenerator;

use Hypnodev\OpenapiGenerator\Attributes\OpenApi\Path;
use Hypnodev\OpenapiGenerator\Attributes\OpenApi\Query;
use Hypnodev\OpenapiGenerator\Attributes\OpenApi\Request;
use Hypnodev\OpenapiGenerator\Attributes\OpenApi\Response;
use Hypnodev\OpenapiGenerator\Exceptions\DefinitionException;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use JsonException;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use ReflectionClass;
use ReflectionMethod;

class DefinitionFile implements Arrayable
{
    private const ALLOWED_TYPES = [
        'string',
        'int',
        'integer',
        'number',
        'date',
        'date_format',
        'url',
        'uuid',
        'email',
        'boolean',
        'bool',
        'array'
    ];

    private const OPENAPI_VERSION = '3.0.3';

    private const DEFAULT_CONTENT_TYPE = 'application/json';

    private array $paths = [];

    private array $schemas = [];

    private Collection $routes;

    public function __construct(
        private readonly array $metadata
    ) {
        $this->routes = collect();
    }

    public function toArray(): array
    {
        if (!array_key_exists('info', $this->metadata)) {
            throw new DefinitionException('[info] property is needed in OpenApi definitions.');
        }

        foreach ($this->routes as $route) {
            $this->createPathEntry($route);
        }

        $servers = array_map(static fn ($url) => ['url' => $url], $this->metadata['servers'] ?? []);
        $tags = array_map(static function (array|string $description, $name) {
            if (is_string($description)) {
                return ['name' => $name, 'description' => $description];
            }

            return [
                'name' => $name,
                'description' => $description['description'],
                'externalDocs' => $description['externalDocs']
            ];
        }, $this->metadata['tags'] ?? [], array_keys($this->metadata['tags'] ?? []));

        return [
            'openapi' => self::OPENAPI_VERSION,
            'info' => $this->metadata['info'] ?? [],
            'externalDocs' => $this->metadata['externalDocs'] ?? [],
            'servers' => $servers,
            'tags' => $tags,
            'paths' => $this->paths,
            'components' => [
                'schemas' => $this->schemas,
                'securitySchemes' => $this->metadata['securitySchemes'] ?? [],
            ],
        ];
    }

    private function createPathEntry(Route $route): void
    {
        $controller = $route->getControllerClass();
        $methodName = is_callable($route->getController()) ? '__invoke' : $route->getActionMethod();
        
        $controllerReflector = new ReflectionClass($controller);
        $controllerMethod = $controllerReflector->getMethod($methodName);

        $security = [];
        $routeMiddlewares = $route->gatherMiddleware();
        $authMiddleware = Arr::first($routeMiddlewares, fn (string $middleware) => Str::startsWith($middleware, 'auth'));
        if ($authMiddleware !== null && Str::contains($authMiddleware, ['sanctum', 'passport', 'jwt', 'api'])) {
            $security[] = [
                'bearerToken' => [],
            ];
        }

        $pathAttribute = ($controllerMethod->getAttributes(Path::class)[0] ?? null)?->getArguments();
        $path = [
            'tags' => $pathAttribute['tags'] ?? [],
            'summary' => $pathAttribute['summary'] ?? "",
            'description' => $pathAttribute['description'] ?? "",
            'operationId' => $pathAttribute['operationId'] ?? "",
            'parameters' => $this->createParameters($controllerMethod),
            'requestBody' => $this->createRequestBody($controllerMethod),
            'responses' => $this->createResponses($controllerMethod),
            'security' => $security,
        ];

        $uri = Str::of($route->uri())->prepend('/')->lower()->__toString();
        $routeMethod = Str::lower($route->methods()[0]);

        $this->paths[$uri] = $this->paths['uri'] ?? [];
        $this->paths[$uri][$routeMethod] = array_filter($path, static fn ($value) => !empty($value));
    }

    private function createParameters(ReflectionMethod $method) : ?array
    {
        if ((! ($parameters = $method->getParameters())) &&
            (!($attributes = $method->getAttributes(Query::class))) &&
            (!$method->getDocComment())) {
            return null;
        }

        // If the first parameter is a FormRequest, remove it from the parameters array
        // we want to handle only the route parameters in this method.
        $requestParameter = ($parameters[0] ?? null)?->getType()?->getName();
        if (($requestParameter !== null) && in_array(get_parent_class($requestParameter), [FormRequest::class, \Illuminate\Http\Request::class])) {
            array_shift($parameters);
        }

        if ($doc = $method->getDocComment()) {
            $parameters ??= [];
            $this->parseQueryFromMethodDocComment($doc, $parameters);
        }

        if (empty($parameters) && empty($attributes)) {
            return null;
        }

        $attributes ??= [];
        foreach ($attributes as $attribute) {
            $parameters[] = $this->makeParameterFromArguments(
                $attribute->getArguments()
            );
        }

        $parametersDefinition = [];
        foreach ($parameters as $parameter) {
            $parameterType = $parameter->getType()?->getName();
            $parameterName = $parameter->getName();
            $allowsNull = $parameter->allowsNull();
            $parameterIn = match (true) {
                $parameterType === 'int', class_exists($parameterType) => 'path',
                $parameterType === 'string' => 'query'
            };

            if ($parameterType === null) {
                throw new DefinitionException("[$method->class@$method->name] Parameter [$parameterName] has no type.");
            }

            $parameterType = match (true) {
                $parameterType === 'int', class_exists($parameterType) => 'integer',
                $parameterType === 'string' => 'string'
            };

            $parameterDefinition = [
                'name' => $parameterName,
                'in' => $parameterIn,
                'required' => match ($parameterIn) {
                    'path' => true,
                    'query' => !$allowsNull,
                },
                'schema' => [
                    'type' => $parameterType,
                ],
            ];

            $parametersDefinition[] = $parameterDefinition;
        }

        return $parametersDefinition;
    }

    private function createRequestBody(ReflectionMethod $method): ?array
    {
        if (! ($parameters = $method->getParameters())
            || (! ($class = $parameters[0]?->getType()?->getName()))) {
            return null;
        }

        if (get_parent_class($class) !== FormRequest::class) {
            return null;
        }

        $reflector = new ReflectionClass($class);
        $attributes = $reflector->getAttributes(Request::class);

        if (!count($attributes)
            || (! ($arguments = $attributes[0]->getArguments()))) {
            return null;
        }

        $formRequest = new $class();
        $schemaName = class_basename($class);
        return [
            'description' => $arguments['description'] ?? '',
            'content' => [
                ($arguments['contentType'] ?? self::DEFAULT_CONTENT_TYPE) => [
                    'schema' => [
                        '$ref' => $this->createSchema($formRequest->rules(), $schemaName),
                    ],
                ],
            ],
            'required' => $arguments['required'] ?? true,
        ];
    }

    private function createSchema(array $schemaRules, string $schemaName): string
    {
        if ($this->schemas[$schemaName] ?? null) {
            return "#/components/schemas/$schemaName";
        }

        $properties = [];
        $required = [];

        foreach ($schemaRules as $ruleName => $rules) {
            $property = $this->getTypeFromRules($rules);

            foreach ($rules as $rule) {
                switch ($rule) {
                    case 'required':
                        $required[] = $ruleName;
                        break;
                    case 'array':
                        $property['type'] = 'array';
                        $property['items'] = [];
                        break;
                    case Str::startsWith($rule, 'in:'):
                        $property['type'] = 'string';
                        $property['enum'] = Str::of($rule)->remove(['in:', "\""])->explode(',')->toArray();
                        break;
                    case Str::contains($ruleName, '.'):
                        [$parentPropertyName, $nestedPropertyName] = explode('.', $ruleName);
                        $ruleName = $parentPropertyName;

                        // If the rule name is something like `example.*`, it means that the property is an array
                        if ($nestedPropertyName === '*') {
                            $property['items'] = $property;
                            break;
                        }

                        $objectProperties = collect($schemaRules)
                            ->filter(static fn (array $values, string $key) => Str::startsWith($key, "$parentPropertyName]."))
                            ->mapWithKeys(fn (array $values, string $key) => [Str::remove("$parentPropertyName.", $key) => $values])
                            ->toArray();

                        $property['items']['$ref'] = $this->createSchema($objectProperties, ucfirst($parentPropertyName));
                        break;
                }
            }

            $properties[$ruleName] = $property;
        }

        // Remove nested properties from the root of the schema
        $properties = array_filter($properties, static fn ($key) => !Str::contains($key, '.'), ARRAY_FILTER_USE_KEY);

        $schema = [
            'type' => 'object',
            'properties' => $properties,
            'required' => $required
        ];
        $this->schemas[$schemaName] = array_filter($schema);

        return "#/components/schemas/$schemaName";
    }

    private function createResponses(ReflectionMethod $method): array
    {
        $responsesDefinition = [];
        $responses = [];

        $methodAttributes = $method->getAttributes(Response::class);
        foreach ($methodAttributes as $methodAttribute) {
            $arguments = $methodAttribute->getArguments();
            $responses[$arguments['statusCode']] = $arguments['response'] ?? null;
        }

        $doc = $method->getDocComment();
        if ($doc !== false) {
            $this->parseResponseFromMethodDocComment($doc, $method, $responses);
        }

        foreach ($responses as $statusCode => $response) {
            if ($response instanceof Model || (is_array($response) && ($response[0] ?? null) instanceof Model)) {
                $modelRules = [];
                $model = $response[0] ?? $response;
                $casts = $model->getCasts();

                foreach ($model->getFillable() as $field) {
                    $modelRules[$field] = $this->getTypeFromRules([
                        !array_key_exists($field, $casts) ? 'string' : $casts[$field],
                    ]);
                }

                $schema = is_array($response)
                    ? ['type' => 'array', 'items' => ['$ref' => $this->createSchema($modelRules, class_basename($model))]]
                    : ['$ref' => $this->createSchema($modelRules, class_basename($model))];
            } elseif (is_array($response)) {
                $schema = [
                    'type' => 'object',
                    'properties' => $this->getPropertiesRecursive($response),
                ];
            } else {
                continue;
            }

            $responsesDefinition[$statusCode] = [
                'description' => $arguments['description'] ?? '',
                'content' => [
                    ($arguments['contentType'] ?? self::DEFAULT_CONTENT_TYPE) => [
                        'schema' => $schema,
                    ],
                ],
            ];
        }

        return $responsesDefinition;
    }

    private function getPropertiesRecursive(array $response): array
    {
        $properties = [];

        foreach ($response as $key => $value) {
            if (!is_array($value)) {
                // If the value is not an array, get its type and use it as the property type.
                $properties[$key] = $this->getTypeFromRules([
                    gettype($value),
                ]);
            } else if (is_array($value[0] ?? null)) {
                $properties[$key] = [
                    'type' => 'array',
                    'items' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => $this->getPropertiesRecursive($value[0]),
                        ],
                    ],
                ];
            } else {
                // If it's a regular array, treat it as an array of objects.
                $properties[$key] = [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => $this->getPropertiesRecursive($value),
                    ],
                ];
            }
        }

        return $properties;
    }


    private function getTypeFromRules(array $rules): ?array
    {
        $type = Arr::first($rules, static fn (string $rule) => in_array($rule, self::ALLOWED_TYPES));
        $type = match (true) {
            in_array($type, ['date', 'date_format', 'url', 'uuid', 'email']) => 'string',
            $type === 'bool' => 'boolean',
            $type === 'int' => 'integer',
            default => $type,
        };

        $format = match (true) {
            in_array($type, ['date', 'date_format']) => Arr::first($rules, static function (string $rule) {
                return Str::startsWith('date_format', $rule) && Str::contains($rule, ['H:i', 'h:i']);
            }) ? 'date-time' : 'date',
            $type === 'url' => 'url',
            $type === 'uuid' => 'uuid',
            $type === 'email' => 'email',
            default => null,
        };

        return $format === null
            ? compact('type')
            : compact('type', 'format');
    }

    public function routes(Collection $routes): DefinitionFile
    {
        $this->routes = $routes;
        return $this;
    }

    private function parseResponseFromMethodDocComment(string $doc, ReflectionMethod $method, array &$responses): void
    {
        // Create necessary parser instances
        $lexer = new Lexer();
        $constExprParser = new ConstExprParser();
        $phpDocParser = new PhpDocParser(
            new TypeParser($constExprParser),
            $constExprParser
        );

        // Tokenize and parse the doc comment
        $tokens = new TokenIterator($lexer->tokenize($doc));
        $phpDocNode = $phpDocParser->parse($tokens);

        // Get OpenApi or Response tags
        $tags = $phpDocNode->getTagsByName('@OpenApi\Response') ?? $phpDocNode->getTagsByName('@Response') ?? [];
        $tags = array_values($tags);

        foreach ($tags as $tag) {
            [$statusCode, $model] = Str::of($tag->value->value)
                ->remove(['(', ')'])
                ->explode(',', 2)
                ->map(fn(string $param) => explode('=', $param)[1] ?? null);

            // If the class exists, treat it as a resource
            if (class_exists($model)) {
                $responses[$statusCode] = new $model();
                continue;
            }

            // If the model isn't a resource, it must be a JSON response, otherwise it's invalid.
            try {
                $model = preg_replace('~\R~', '', $model);
                $responses[$statusCode] = json_decode($model, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                throw new DefinitionException("[$method->class@$method->name] JSON response with status code [$statusCode] is not valid.");
            }
        }
    }

    private function parseQueryFromMethodDocComment(string $doc, array &$parameters): void
    {
        // Create necessary parser instances
        $lexer = new Lexer();
        $constExprParser = new ConstExprParser();
        $phpDocParser = new PhpDocParser(
            new TypeParser($constExprParser),
            $constExprParser
        );

        // Tokenize and parse the doc comment
        $tokens = new TokenIterator($lexer->tokenize($doc));
        $phpDocNode = $phpDocParser->parse($tokens);

        // Get OpenApi or Response tags
        $tags = $phpDocNode->getTagsByName('@OpenApi\Query') ?? $phpDocNode->getTagsByName('@Query') ?? [];
        $tags = array_values($tags);

        foreach ($tags as $tag) {
            $param = Str::of($tag->value->value)
                ->remove(['(', ')'])
                ->explode(',', 2)
                ->map(fn(string $param) => explode('=', $param)[1] ?? null);

            $name = $param[0];
            $required = $param[1] === 'true';

            $parameters[] = $this->makeParameterFromArguments(
                compact('name', 'required')
            );
        }
    }

    private function makeParameterFromArguments(array $arguments) : object
    {
        return new class($arguments) {
            public function __construct(
                private readonly array $arguments
            ) {
                //
            }

            public function getType(): object
            {
                return new class() {
                    public function getName(): string
                    {
                        return 'string';
                    }
                };
            }

            public function getName(): string
            {
                return $this->arguments['name'];
            }

            public function allowsNull() : bool
            {
                return !$this->arguments['required'];
            }
        };
    }
}
