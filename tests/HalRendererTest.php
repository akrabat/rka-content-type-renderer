<?php
namespace RKA\ContentTypeRenderer\Tests;

use RKA\ContentTypeRenderer\HalRenderer as Renderer;
use Nocarrier\Hal;
use Zend\Diactoros\Request;
use Zend\Diactoros\Uri;
use Zend\Diactoros\Response;
use RuntimeException;

class HalRendererTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test that a given array is rendered to the correct media type
     *
     * @dataProvider rendererProvider
     */
    public function testRenderer($mediaType, $data, $expectedMediaType, $expectedBody, $pretty)
    {
        $renderer = new Renderer($pretty);

        $request = (new Request())
            ->withUri(new Uri('http://example.com'))
            ->withAddedHeader('Accept', $mediaType);

        $response = new Response();

        $response  = $renderer->render($request, $response, $data);

        $this->assertSame($expectedMediaType, $response->getHeaderLine('Content-Type'));
        $this->assertSame($expectedBody, (string)$response->getBody());
    }

    /**
     * Data provider for testRenderer()
     *
     * Array format:
     *     0 => Accept header media type in Request
     *     1 => Data array to be rendered
     *     2 => Expected media type in Response
     *     3 => Expected body string in Response
     *
     * @return array
     */
    public function rendererProvider()
    {
        $items = [
                    [
                        'name' => 'Alex',
                        'is_admin' => true,
                    ],
                    [
                        'name' => 'Robin',
                        'is_admin' => false,
                    ],
                ];

        $data = new Hal(
            '/foo',
            [
                'items' => $items,
            ]
        );

        $expectedJson = json_encode([
            'items' => $items,
            '_links' => [
                'self' => [
                    'href' => '/foo',
                ],
            ]
        ]);

        $expectedPrettyJson = json_encode([
            'items' => $items,
            '_links' => [
                'self' => [
                    'href' => '/foo',
                ],
            ]
        ], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);

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

        return [
            ['application/hal+json', $data, 'application/hal+json', $expectedJson, false],
            ['application/anything+json', $data, 'application/hal+json', $expectedJson, false],
            ['application/json', $data, 'application/hal+json', $expectedJson, false],
            ['application/json', $data, 'application/hal+json', $expectedPrettyJson, true],
            ['application/hal+xml', $data, 'application/hal+xml', $expectedXML, false],
            ['application/anything+xml', $data, 'application/hal+xml', $expectedXML, false],
            ['application/xml', $data, 'application/hal+xml', $expectedXML, false],
            ['text/xml', $data, 'application/hal+xml', $expectedXML, false],
            ['text/html', $data, 'text/html', $expectedHTML, false],

            // specific media type wins
            ['application/xml,application/hal+json', $data, 'application/hal+json', $expectedJson, false],
        ];
    }


    /**
     * The data has to be a Hal object
     */
    public function testCaseWhenDataIsNotAHalObject()
    {
        $data = 'Alex';

        $request = (new Request())
            ->withUri(new Uri('http://example.com'))
            ->withAddedHeader('Accept', 'application/json');
        $response = new Response();
        $renderer = new Renderer();

        $this->setExpectedException(RuntimeException::class, 'Data is not a Hal object');
        $response  = $renderer->render($request, $response, $data);
    }
}
