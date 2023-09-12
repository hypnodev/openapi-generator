<?php

namespace Hypnodev\OpenapiGenerator\Attributes\OpenApi;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Path
{
    public function __construct(
        public array $tags = [],
        public string $summary = '',
        public string $description = '',
        public string $operationId = ''
    ) {
        //
    }
}
