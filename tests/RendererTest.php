<?php
namespace RKA\ContentTypeRenderer\Tests;

use RKA\ContentTypeRenderer\Renderer;
use Zend\Diactoros\Request;
use Zend\Diactoros\Uri;
use Zend\Diactoros\Response;
use Zend\Diactoros\Stream;

class RendererTest extends \PHPUnit_Framework_TestCase
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
        ]);

        $expectedXML = '<?xml version="1.0" encoding="UTF-8"?>
<root>
  <items>
    <name>Alex</name>
    <is_admin>true</is_admin>
  </items>
  <items>
    <name>Robin</name>
    <is_admin>false</is_admin>
  </items>
</root>
';

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
            [$renderer, 'text/csv', $data, 'application/json', $expectedJson], // default to JSON for unknown content type
            [$htmlRenderer, 'text/csv', $data, 'text/html', $expectedHTML], // default to HTML in this case for unknown content type
        ];
    }

    /**
     * If the stream in the Response is not writable, then we need to replace
     * it with our own SimplePsrStream
     */
    public function testUseOurOwnStreamIfCurrentOneIsNotWritable()
    {
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

        $request = (new Request())
            ->withUri(new Uri('http://example.com'))
            ->withAddedHeader('Accept', 'application/json');

        $response = new Response();
        $response = $response->withBody(new Stream('php://temp', 'r'));

        $renderer = new Renderer();
        $response  = $renderer->render($request, $response, $data);

        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame(json_encode($data), (string)$response->getBody());
        $this->assertInstanceOf('RKA\ContentTypeRenderer\SimplePsrStream', $response->getBody());
    }

    /**
     * If the stream in the Response cannot be rewound, then we need to replace
     * it with our own SimplePsrStream
     */
    public function testUseOurOwnStreamIfCurrentOneIsNotRewindable()
    {
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

        $request = (new Request())
            ->withUri(new Uri('http://example.com'))
            ->withAddedHeader('Accept', 'application/json');

        stream_wrapper_register("norewind", NonRewindableStream::class);

        $response = new Response();
        $response = $response->withBody(new Stream('norewind://temp', 'a'));

        $renderer = new Renderer();
        $response  = $renderer->render($request, $response, $data);

        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame(json_encode($data), (string)$response->getBody());
        $this->assertInstanceOf('RKA\ContentTypeRenderer\SimplePsrStream', $response->getBody());
    }
}
