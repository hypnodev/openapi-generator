<?php

return [
    'definition' => [
        'info' => [
            'version' => '1.0',
            'title' => env('OPENAPI_TITLE', env('APP_NAME', '').' - OpenAPI 3.0'),
            'description' => 'Documentation of our API that follows OpenAPI 3.0 specification.',
            'termsOfService' => env('OPENAPI_TOS', ''),
            'contact' => [
                'name' => env('OPENAPI_CONTACT_NAME', ''),
                'url' => env('OPENAPI_CONTACT_URL', ''),
                'email' => env('OPENAPI_CONTACT_EMAIL', '')
            ],
            'license' => [
                'name' => env('OPENAPI_LICENSE_NAME', ''),
                'url' => env('OPENAPI_LICENSE_URL', '')
            ],
        ],
        'externalDocs' => [
            'description' => 'Find out more about OpenAPI 3.0',
            'url' => 'https://swagger.io/specification/'
        ],
        'servers' => [
            env('APP_URL').'/api/v1',
        ],
        'tags' => [
            'example' => 'This is an example tag',
            'example_with_docs' => [
                'description' => 'This is an example tag with documentation',
                'externalDocs' => [
                    'description' => 'Find out more',
                    'url' => 'http://swagger.io'
                ]
            ]
        ],
        'securitySchemes' => [
            'bearerToken' => [
                'type' => 'http',
                'scheme' => 'bearer',
                'bearerFormat' => 'JWT',
            ]
        ]
    ],
];
