<?php
namespace RKA\ContentTypeRenderer;

use Crell\ApiProblem\ApiProblem;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

class ApiProblemRenderer extends Renderer
{
    /**
     * Pretty print output (default true)
     * @var bool
     */
    protected $pretty;

    public function __construct($pretty = true)
    {
        $this->pretty = (bool)$pretty;
    }

    public function render(RequestInterface $request, ResponseInterface $response, $problem)
    {
        $format = $this->determinePeferredFormat($request->getHeaderLine('Accept'), ['json', 'xml'], 'json');

        // set the ProblemAPi content type for JSON or XML
        $output = $this->renderOutput($format, $problem);
        $contentType = 'application/problem+' . $format;
        
        $response = $this->writeBody($response, $output);
        $response = $response->withHeader('Content-type', $contentType);
        
        return $response;
    }

    protected function renderOutput($format, $problem)
    {
        if (!$problem instanceof ApiProblem) {
            throw new RuntimeException('Data is not an ApiProblem object');
        }

        if ($format == 'xml') {
            return $problem->asXml($this->pretty);
        }

        return $problem->asJson($this->pretty);
    }
}
