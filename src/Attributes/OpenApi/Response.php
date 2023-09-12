<?php

namespace Hypnodev\OpenapiGenerator\Attributes\OpenApi;

use Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * @Annotation
 * @NamedArgumentConstructor
 * @Target({"METHOD"})
 * @Attributes({
 *     @Attribute("statusCode", type="int", required=true),
 *     @Attribute("contentType", type="string", required=false),
 *     @Attribute("description", type="string", required=false),
 *     @Attribute("response", type="mixed", required=false),
 *     )}
 *
 * @property-read int $statusCode
 * @property-read string $contentType
 * @property-read string $description
 * @property-read string|array|Model $response
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Response
{
    public function __construct(
        public int $statusCode,
        public string $contentType = 'application/json',
        public string $description = '',
        public string|array|Model $response = []
    ) {
        //
    }
}
