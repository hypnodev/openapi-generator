<?php

namespace Hypnodev\OpenapiGenerator\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array make(string $prefix = 'api')
 *
 * @see \Hypnodev\OpenapiGenerator\OpenapiGenerator
 */
class OpenapiGenerator extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'openapi-generator';
    }
}
