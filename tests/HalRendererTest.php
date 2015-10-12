<?php
namespace RKA\ContentTypeRenderer\Tests;

use RKA\ContentTypeRenderer\HalRenderer as Renderer;
use Nocarrier\Hal;
use Zend\Diactoros\Request;
use Zend\Diactoros\Uri;
use Zend\Diactoros\Response;
use Zend\Diactoros\Stream;

class HalRendererTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test that a given array is rendered to the correct content type
     *
     * @dataProvider rendererProvider
     */
    public function testRenderer($renderer, $contentType, $data, $expectedContentType, $expectedBody)
    {
        $request = (new Request())
            ->withUri(new Uri('http://example.com'))
            ->withAddedHeader('Accept', $contentType);

        $response = new Response();

        $response  = $renderer->render($request, $response, $data);

        $this->assertSame($expectedContentType, $response->getHeaderLine('Content-Type'));
        $this->assertSame($expectedBody, (string)$response->getBody());
        $this->assertInstanceOf('Zend\Diactoros\Stream', $response->getBody());
    }

    /**
     * Data provider for testRenderer()
     *
     * Array format:
     *     0 => Renderer
     *     1 => Accept header content type in Request
     *     2 => Data array to be rendered
     *     3 => Expected content type in Response
     *     4 => Expected body string in Response
     *
     * @return array
     */
    public function rendererProvider()
    {
        $data = new Hal(
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

        $expectedJson = json_encode([
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
            '_links' => [
                'self' => [
                    'href' => '/foo',
                ],
            ]
        ]);

        $expectedXML = '<?xml version="1.0"?>' . PHP_EOL
                    . '<resource href="/foo"><items><name>Alex</name><is_admin>1</is_admin></items>'
                    . '<items><name>Robin</name><is_admin>0</is_admin></items></resource>'
                    . PHP_EOL;

        $expectedHTML = '<!DOCTYPE html>
<html>
<head>
    <title></title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <style>
    body {
        font-family: Helvetica, Arial, sans-serif;
        font-size: 14px;
        color: #000;
        padding: 5px;
    }

    ul {
        padding-bottom: 15px;
        padding-left: 20px;
    }
    a {
        color: #2368AF;
    }
    </style>
</head>
<body><ul>
<li><strong>items:</strong> <ul>
<li><strong>0:</strong> <ul>
<li><strong>name:</strong> Alex</li>
<li><strong>is_admin:</strong> true</li>
</ul>
</li>
<li><strong>1:</strong> <ul>
<li><strong>name:</strong> Robin</li>
<li><strong>is_admin:</strong> false</li>
</ul>
</li>
</ul>
</li>
<li><strong>_links:</strong> <ul>
<li><strong>self:</strong> <ul>
<li><strong>href:</strong> /foo</li>
</ul>
</li>
</ul>
</li>
</ul>
</body>
</html>
';

        $renderer = new Renderer();
        $htmlRenderer = new Renderer();
        $htmlRenderer->setDefaultContentType('text/html');
            
        return [
            [$renderer, 'application/json', $data, 'application/json', $expectedJson],
            [$renderer, 'application/xml', $data, 'application/xml', $expectedXML],
            [$renderer, 'text/xml', $data, 'text/xml', $expectedXML],
            [$renderer, 'text/html', $data, 'text/html', $expectedHTML],

            // default to JSON for unknown content type
            [$renderer, 'text/csv', $data, 'application/json', $expectedJson],

            // default to HTML in this case for unknown content type
            [$htmlRenderer, 'text/csv', $data, 'text/html', $expectedHTML],
        ];
    }
}
