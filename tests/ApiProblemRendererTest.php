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
    public function testRenderer($mediaType, $problem, $expectedMediaType, $expectedBody, $pretty)
    {
        $renderer = new Renderer($pretty);

        $request = (new Request())
            ->withUri(new Uri('http://example.com'))
            ->withAddedHeader('Accept', $mediaType);

        $response = new Response();

        $response  = $renderer->render($request, $response, $problem);

        $this->assertSame($expectedMediaType, $response->getHeaderLine('Content-Type'));
        $this->assertSame($expectedBody, (string)$response->getBody());

        if ($problem->getStatus()) {
            $this->assertSame($problem->getStatus(), $response->getStatusCode());
        }
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
        $problem = new ApiProblem("foo");
        $problem->setStatus(400);
        
        $outputData = [
            'title' => 'foo',
            'type' => 'about:blank',
            'status' => 400,
        ];


        $expectedJson = json_encode($outputData);
        $expectedPrettyJson = json_encode($outputData, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);

        $expectedXML = '<?xml version="1.0"?>' . PHP_EOL
                    . '<problem><title>foo</title><type>about:blank</type><status>400</status></problem>'
                    . PHP_EOL;

        return [
            ['application/hal+json', $problem, 'application/problem+json', $expectedJson, false],
            ['application/json', $problem, 'application/problem+json', $expectedJson, false],
            ['vnd.foo/anything+json', $problem, 'application/problem+json', $expectedJson, false],
            ['application/json', $problem, 'application/problem+json', $expectedPrettyJson, true],
            ['application/hal+xml', $problem, 'application/problem+xml', $expectedXML, false],
            ['application/xml', $problem, 'application/problem+xml', $expectedXML, false],
            ['text/xml', $problem, 'application/problem+xml', $expectedXML, false],
            ['vnd.foo/anything+xml', $problem, 'application/problem+xml', $expectedXML, false],
            ['text/html', $problem, 'application/problem+json', $expectedJson, false],

            // Specific media type wins
            ['application/hal+json,application/problem+xml', $problem, 'application/problem+xml', $expectedXML, false],
        ];
    }

    /**
     * The data has to be an ApiProblem object
     */
    public function testCaseWhenDataIsNotAnApiProblemObject()
    {
        $problem = 'Alex';

        $request = (new Request())
            ->withUri(new Uri('http://example.com'))
            ->withAddedHeader('Accept', 'application/json');
        $response = new Response();
        $renderer = new Renderer();

        $this->setExpectedException(RuntimeException::class, 'Data is not an ApiProblem object');
        $response  = $renderer->render($request, $response, $problem);
    }
}
