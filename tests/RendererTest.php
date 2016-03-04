<?php
namespace RKA\ContentTypeRenderer\Tests;

use RKA\ContentTypeRenderer\Renderer;
use Zend\Diactoros\Request;
use Zend\Diactoros\Uri;
use Zend\Diactoros\Response;
use Zend\Diactoros\Stream;
use RuntimeException;

class RendererTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test that a given array is rendered to the correct media type
     *
     * @dataProvider rendererProvider
     */
    public function testRenderer($mediaType, $data, $expectedMediaType, $expectedBody, $defaultMediaType)
    {
        $renderer = new Renderer();
        if ($defaultMediaType) {
            $renderer->setDefaultMediaType($defaultMediaType);
        }

        $request = (new Request())
            ->withUri(new Uri('http://example.com'))
            ->withAddedHeader('Accept', $mediaType);

        $response = new Response();

        $response  = $renderer->render($request, $response, $data);

        $this->assertSame($expectedMediaType, $response->getHeaderLine('Content-Type'));
        $this->assertSame($expectedBody, (string)$response->getBody());
        $this->assertInstanceOf(Stream::class, $response->getBody());
    }

    /**
     * Data provider for testRenderer()
     *
     * Array format:
     *     0 => Accept header media type in Request
     *     1 => Data array to be rendered
     *     2 => Expected media type in Response
     *     3 => Expected body string in Response
     *     4 => Default media type for Renderer
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
                    'link' => 'http://example.com',
                ],
            ],
        ];

        $expectedJson = json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);

        $expectedXML = '<?xml version="1.0"?>
<root>
  <items>
    <name>Alex</name>
    <is_admin>1</is_admin>
  </items>
  <items>
    <name>Robin</name>
    <is_admin>0</is_admin>
    <link>http://example.com</link>
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
<li><strong>link:</strong> <a href="http://example.com">http://example.com</a></li>
</ul>
</li>
</ul>
</li>
</ul>
</body>
</html>
';

        $expectedXML2 = '<?xml version="1.0"?>
<root>
  <_0>1</_0>
  <foo>bar</foo>
  <_1>3</_1>
</root>
';

        return [
            ['application/json', $data, 'application/json', $expectedJson, null],
            ['application/xml', $data, 'application/xml', $expectedXML, null],
            ['text/xml', $data, 'text/xml', $expectedXML, null],
            ['text/html', $data, 'text/html', $expectedHTML, null],

            // default to JSON for unknown media type
            ['text/csv', $data, 'application/json', $expectedJson, null],
            
            // default to HTML in this case for unknown media type
            ['text/csv', $data, 'text/html', $expectedHTML, 'text/html'],

            // Numeric array indexes can cause trouble for XML
            ['text/xml', [[1], 'foo'=>'bar', 3], 'text/xml', $expectedXML2, null],
        ];
    }

    /**
     * The data has to be an array if accept header is XML
     */
    public function testCaseWhenDataIsNotScalarOrArray()
    {
        $data = new \stdClass();

        $request = (new Request())
            ->withUri(new Uri('http://example.com'))
            ->withAddedHeader('Accept', 'application/json');
        $response = new Response();
        $renderer = new Renderer();

        $this->setExpectedException(RuntimeException::class, 'Data must be of type scalar or array');
        $response  = $renderer->render($request, $response, $data);
    }

    /**
     * The data has to be an array
     */
    public function testCaseWhenDataIsNotAnArrayAndAcceptIsXml()
    {
        $data = 'Alex';

        $request = (new Request())
            ->withUri(new Uri('http://example.com'))
            ->withAddedHeader('Accept', 'text/xml');
        $response = new Response();
        $renderer = new Renderer();

        $this->setExpectedException(RuntimeException::class, 'Data is not an array');
        $response  = $renderer->render($request, $response, $data);
    }

    /**
     * Can change the surrounding HTML
     */
    public function testCustomHtml()
    {
        $data = [
            'items' => [
                [
                    'name' => 'Alex',
                    'is_admin' => true,
                ],
            ],
        ];

        $request = (new Request())
            ->withUri(new Uri('http://example.com'))
            ->withAddedHeader('Accept', 'text/html');
        $response = new Response();
        $renderer = new Renderer();

        $renderer->setHtmlPrefix('');
        $renderer->setHtmlPostfix('');

        $response  = $renderer->render($request, $response, $data);

        $expectedBody = '<ul>
<li><strong>items:</strong> <ul>
<li><strong>0:</strong> <ul>
<li><strong>name:</strong> Alex</li>
<li><strong>is_admin:</strong> true</li>
</ul>
</li>
</ul>
</li>
</ul>
';

        $this->assertSame('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertSame($expectedBody, (string)$response->getBody());

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
        $this->assertSame(json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES), (string)$response->getBody());
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
        $this->assertSame(json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES), (string)$response->getBody());
        $this->assertInstanceOf('RKA\ContentTypeRenderer\SimplePsrStream', $response->getBody());
    }
}
