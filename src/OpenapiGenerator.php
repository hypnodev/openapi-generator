<?php

namespace Hypnodev\OpenapiGenerator;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;

class OpenapiGenerator
{
    public function __construct(
        private readonly array $metadata
    ) {
        //
    }

    public function make(string $prefix = 'api'): array
    {
        $regex = preg_quote(trim($prefix, '/'), '/');
        $routeFilter = fn (Route $route) =>
        preg_match('/^' . $regex . '((\/.+)|$)/', $route->uri,)
            &&
            $route->getActionName() !== 'Closure';
            
        $routes = collect(Router::getRoutes())
            ->filter($routeFilter);

        $definitionFile = new DefinitionFile($this->metadata);
        $definitionFile->routes($routes);
        return $definitionFile->toArray();
    }
}
