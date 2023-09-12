<?php

namespace Hypnodev\OpenapiGenerator;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Illuminate\Support\Str;

class OpenapiGenerator
{
    public function __construct(
        private readonly array $metadata
    ) {
        //
    }

    public function make(string $prefix = 'api'): array
    {
        $routes = collect(Router::getRoutes())->filter(function (Route $route) use ($prefix) {
            return Str::contains($route->uri(), Str::remove('/', $prefix))
                && $route->getActionName() !== 'Closure';
        });

        $definitionFile = new DefinitionFile($this->metadata);
        $definitionFile->routes($routes);
        return $definitionFile->toArray();
    }
}
