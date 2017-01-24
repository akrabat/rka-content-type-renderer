# Render output based on content-type

Render an array (or HAL object) to a JSON/XML/HTML PSR-7 Response based on a PSR-7 Request's Accept header.

[![Build status][Master image]][Master]

## Installation

`composer require akrabat/rka-content-type-renderer`

## Usage

```php
// given:
// $request instanceof Psr\Http\Message\RequestInterface
// $response instanceof Psr\Http\Message\ResponseInterface

$data = [
    'items' => [
        [
            'name' => 'Alex',
            'is_admin' => true,
        ],
        [
            'name' => 'Robin',
            'is_admin' => false,
        ],
    ],
];
$renderer = new RKA\ContentTypeRenderer\Renderer();
$response  = $renderer->render($request, $response, $data);
return $response->withStatus(200);
```

## HalRenderer

This component also supports [nocarrier/hal][hal] objects with the `HalRenderer`:

```php
$hal = new Nocarrier\Hal(
    '/foo',
    [
        'items' => [
            [
                'name' => 'Alex',
                'is_admin' => true,
            ],
            [
                'name' => 'Robin',
                'is_admin' => false,
            ],
        ],
    ]
);
$renderer = new RKA\ContentTypeRenderer\HalRenderer();
$response  = $renderer->render($request, $response, $hal);
return $response->withStatus(200);
```

## ApiRenderer

This component also supports [crell/ApiProblem][ApiProblem] objects with the `ApiProblemRenderer`:

```php
$problem = new Crell\ApiProblem("Something unexpected happened");
$renderer = new RKA\ContentTypeRenderer\ApiProblemRenderer();
$response  = $renderer->render($request, $response, $problem);
return $response->withStatus(500);
```

## Arrays of objects

If you have an array of objects, then the renderer will still work as long
as the objects implement PHP's JsonSerializable interface.

## Testing

* Code style: ``$ phpcs``
* Unit tests: ``$ phpunit``
* Code coverage: ``$ phpunit --coverage-html ./build``



[Master]: https://travis-ci.org/akrabat/rka-content-type-renderer
[Master image]: https://secure.travis-ci.org/akrabat/rka-content-type-renderer.svg?branch=master
[hal]: https://github.com/blongden/hal
[ApiProblem]: https://github.com/Crell/ApiProblem
