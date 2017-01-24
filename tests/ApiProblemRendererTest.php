<?php
namespace RKA\ContentTypeRenderer\Tests;

use RKA\ContentTypeRenderer\ApiProblemRenderer as Renderer;
use Crell\ApiProblem\ApiProblem;
use Zend\Diactoros\Request;
use Zend\Diactoros\Uri;
use Zend\Diactoros\Response;
use RuntimeException;

class ApiProblemRendererTest extends \PHPUnit_Framework_TestCase
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
        $data = new ApiProblem("foo");
        
        $outputData = [
            'title' => 'foo',
            'type' => 'about:blank',
        ];


        $expectedJson = json_encode($outputData);
        $expectedPrettyJson = json_encode($outputData, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);

        $expectedXML = '<?xml version="1.0"?>' . PHP_EOL
                    . '<problem><title>foo</title><type>about:blank</type></problem>'
                    . PHP_EOL;

        return [
            ['application/hal+json', $data, 'application/problem+json', $expectedJson, false],
            ['application/json', $data, 'application/problem+json', $expectedJson, false],
            ['vnd.foo/anything+json', $data, 'application/problem+json', $expectedJson, false],
            ['application/json', $data, 'application/problem+json', $expectedPrettyJson, true],
            ['application/hal+xml', $data, 'application/problem+xml', $expectedXML, false],
            ['application/xml', $data, 'application/problem+xml', $expectedXML, false],
            ['text/xml', $data, 'application/problem+xml', $expectedXML, false],
            ['vnd.foo/anything+xml', $data, 'application/problem+xml', $expectedXML, false],
            ['text/html', $data, 'application/problem+json', $expectedJson, false],
        ];
    }

    /**
     * The data has to be an ApiProblem object
     */
    public function testCaseWhenDataIsNotAnApiProblemObject()
    {
        $data = 'Alex';

        $request = (new Request())
            ->withUri(new Uri('http://example.com'))
            ->withAddedHeader('Accept', 'application/json');
        $response = new Response();
        $renderer = new Renderer();

        $this->setExpectedException(RuntimeException::class, 'Data is not an ApiProblem object');
        $response  = $renderer->render($request, $response, $data);
    }
}
