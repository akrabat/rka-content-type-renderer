<?php
namespace RKA\ContentTypeRenderer\Tests;

use PHPUnit\Framework\TestCase;
use RKA\ContentTypeRenderer\Renderer;
use RuntimeException;
use Zend\Diactoros\Request;
use Zend\Diactoros\Response;
use Zend\Diactoros\Stream;
use Zend\Diactoros\Uri;

class RendererTest extends TestCase
{
    public function determinePeferredFormatProvider()
    {
        return [
            [   // normal
                'application/json', ['json', 'xml', 'html'], null, 'json'
            ],
            [   // invalid accept header
                'tex', ['json', 'xml', 'html'], 'json', 'json'
            ],
            [   // empty accept header
                '', ['json', 'xml', 'html'], 'json', 'json'
            ],
            [   // accept header not in list
                'text/csv', ['json', 'xml', 'html'], 'json', 'json'
            ],
            [   // no allowed formats
                'text/csv', [], 'json', 'json'
            ],
        ];
    }

    /**
     * @dataProvider determinePeferredFormatProvider
     */
    public function testDeterminePreferredFormat($acceptHeader, $allowedFormats, $defaultFormat, $expectedFormat)
    {

        $renderer = new Renderer();
        $format = $renderer->determinePeferredFormat($acceptHeader, $allowedFormats, $defaultFormat);

        $this->assertSame($expectedFormat, $format);
    }


    /**
     * Test that a given array is rendered to the correct media type
     *
     * @dataProvider rendererProvider
     */
    public function testRenderer($pretty, $mediaType, $data, $expectedMediaType, $expectedBody, $defaultMediaType)
    {
        $renderer = new Renderer($pretty);
        if ($defaultMediaType) {
            $renderer->setDefaultMediaType($defaultMediaType);
        }

        $request = (new Request())
            ->withUri(new Uri('http://example.com'))
            ->withAddedHeader('Accept', $mediaType);

        $response = new Response();

        $response = $renderer->render($request, $response, $data);

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

        $dataArray = [
            'items' => [
                [
                    'name'     => 'Alex',
                    'is_admin' => true,
                ],
                [
                    'name'     => 'Robin',
                    'is_admin' => false,
                    'link'     => 'http://example.com',
                ],
            ],
        ];

        $dataScalar = 'Hello World';

        $dataSerializableClass = new Support\SerializableClass($dataArray);

        $expectedJson = json_encode($dataArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $expectedNonPrettyJson = json_encode($dataArray);

        $expectedScalarJson = '"Hello World"';

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

        $expectedNonPrettyXML = '<?xml version="1.0"?>'
            . "\n"
            .'<root><items><name>Alex</name><is_admin>1</is_admin></items><items><name>Robin</name>'
            . '<is_admin>0</is_admin><link>http://example.com</link></items></root>'
            . "\n";

        $expectedXML2 = '<?xml version="1.0"?>
<root>
  <_0>1</_0>
  <foo>bar</foo>
  <_1>3</_1>
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

        $expectedScalarHTML = '<!DOCTYPE html>
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
<body>Hello World</body>
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
            [true, 'application/json', $dataArray, 'application/json', $expectedJson, null],
            [true, 'application/json', $dataScalar, 'application/json', $expectedScalarJson, null],
            [true, 'application/json', $dataSerializableClass, 'application/json', $expectedJson, null],
            [true, 'application/xml', $dataArray, 'application/xml', $expectedXML, null],
            [true, 'application/xml', $dataSerializableClass, 'application/xml', $expectedXML, null],
            [true, 'text/xml', $dataArray, 'text/xml', $expectedXML, null],
            [true, 'text/xml', $dataSerializableClass, 'text/xml', $expectedXML, null],
            [true, 'text/html', $dataArray, 'text/html', $expectedHTML, null],
            [true, 'text/html', $dataScalar, 'text/html', $expectedScalarHTML, null],
            [true, 'text/html', $dataSerializableClass, 'text/html', $expectedHTML, null],

            // default to JSON for unknown media type
            [true, 'text/csv', $dataArray, 'application/json', $expectedJson, null],

            // default to HTML in this case for unknown media type
            [true, 'text/csv', $dataArray, 'text/html', $expectedHTML, 'text/html'],

            // Numeric array indexes can cause trouble for XML
            [true, 'text/xml', [[1], 'foo' => 'bar', 3], 'text/xml', $expectedXML2, null],

            // Pretty can be turned off
            [false, 'application/json', $dataArray, 'application/json', $expectedNonPrettyJson, null],
            [false, 'application/xml', $dataArray, 'application/xml', $expectedNonPrettyXML, null],
        ];
    }

    /**
     * Test that given data type, which is not allowed by given media type, throws an exception
     *
     * @dataProvider rendererErrorsProvider
     */
    public function testRendererErrors($mediaType, $data, $error)
    {
        $request  = (new Request())
            ->withUri(new Uri('http://example.com'))
            ->withAddedHeader('Accept', $mediaType);
        $response = new Response();
        $renderer = new Renderer();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($error);

        $response = $renderer->render($request, $response, $data);
    }

    /**
     * Data provider for testRendererErrors()
     *
     * Array format:
     *     0 => Accept header media type in Request
     *     1 => Data array to be rendered
     *     2 => Expected error string
     *
     * @return array
     */
    public function rendererErrorsProvider()
    {
        $class     = new \stdClass();
        $scalar    = 'Hello World';
        $ressource = fopen('php://input', 'r');

        $xmlError  = 'Data for mediaType text/xml must be array or JsonSerializable';
        $htmlError = 'Data for mediaType text/html must be scalar or array or JsonSerializable';
        $jsonError = 'Data for mediaType application/json must be scalar or array or JsonSerializable';

        return [
            ['application/json', $class, $jsonError],
            ['application/json', $ressource, $jsonError],
            ['text/xml', $class, $xmlError],
            ['text/xml', $scalar, $xmlError],
            ['text/xml', $ressource, $xmlError],
            ['text/html', $class, $htmlError],
            ['text/html', $ressource, $htmlError]
        ];
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

        $response = $renderer->render($request, $response, $data);

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
     * Change root element name
     */
    public function testXmlWithCustomRootElement()
    {
        $dataArray = [
            'items' => [
                [
                    'name'     => 'Alex',
                    'is_admin' => true,
                ],
                [
                    'name'     => 'Robin',
                    'is_admin' => false,
                    'link'     => 'http://example.com',
                ],
            ],
        ];

        $expectedXMLCustomRoot = '<?xml version="1.0"?>
<users>
  <items>
    <name>Alex</name>
    <is_admin>1</is_admin>
  </items>
  <items>
    <name>Robin</name>
    <is_admin>0</is_admin>
    <link>http://example.com</link>
  </items>
</users>
';
        $request = (new Request())
            ->withUri(new Uri('http://example.com'))
            ->withAddedHeader('Accept', 'application/xml');

        $response = new Response();
        $renderer = new Renderer();

        $renderer->setXmlRootElementName('users');

        $response = $renderer->render($request, $response, $dataArray);

        $this->assertSame('application/xml', $response->getHeaderLine('Content-Type'));
        $this->assertSame($expectedXMLCustomRoot, (string)$response->getBody());
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
        $response = $renderer->render($request, $response, $data);

        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), (string)$response->getBody());
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
        $response = $renderer->render($request, $response, $data);

        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), (string)$response->getBody());
        $this->assertInstanceOf('RKA\ContentTypeRenderer\SimplePsrStream', $response->getBody());
    }
}
