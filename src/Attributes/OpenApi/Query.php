<?php

namespace Hypnodev\OpenapiGenerator\Attributes\OpenApi;

use Attribute;

/**
 * @Annotation
 * @NamedArgumentConstructor
 * @Target({"METHOD"})
 * @Attributes({
 *     @Attribute("name", type="string", required=true),
 *     @Attribute("required", type="bool", required=false),
 *     )}
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Query
{
    public function __construct(
        public string $name,
        public bool $required = false,
    ) {
        //
    }
}
