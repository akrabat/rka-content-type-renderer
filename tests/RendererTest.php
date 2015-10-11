<?php
namespace RKA\ContentTypeRenderer\Tests;

use RKA\ContentTypeRenderer\Renderer;
use Zend\Diactoros\Request;
use Zend\Diactoros\Uri;
use Zend\Diactoros\Response;
use Zend\Diactoros\Stream;

class RendererTest extends \PHPUnit_Framework_TestCase
{
    public function testJson()
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

        $renderer = new Renderer();
        $response  = $renderer->render($request, $response, $data);

        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame(json_encode($data), (string)$response->getBody());
        $this->assertInstanceOf('Zend\Diactoros\Stream', $response->getBody());
    }

    public function testXml()
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
            ->withAddedHeader('Accept', 'application/xml');

        $response = new Response();

        $renderer = new Renderer();
        $response  = $renderer->render($request, $response, $data);

        $this->assertSame('application/xml', $response->getHeaderLine('Content-Type'));

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
        $this->assertSame($expectedXML, (string)$response->getBody());
    }

    public function testHtml()
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
            ->withAddedHeader('Accept', 'text/html');

        $response = new Response();

        $renderer = new Renderer();
        $response  = $renderer->render($request, $response, $data);

        $this->assertSame('text/html', $response->getHeaderLine('Content-Type'));

        $expected = '<!DOCTYPE html>
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
<li><strong>items:</strong> </li>
</ul>
</body>
</html>
';
        $this->assertSame($expected, (string)$response->getBody());
    }

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
