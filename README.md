# Render output based on content-type

Render an array to a JSON/XML/HTML PSR-7 Response based on a PSR-7 Request's Accept header.

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
