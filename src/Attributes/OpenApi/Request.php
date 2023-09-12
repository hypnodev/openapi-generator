<?php

namespace Hypnodev\OpenapiGenerator\Attributes\OpenApi;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Request
{
    public function __construct(
        public string $description = '',
        public string $contentType = 'application/json',
        public bool $required = true
    ) {
        //
    }
}
