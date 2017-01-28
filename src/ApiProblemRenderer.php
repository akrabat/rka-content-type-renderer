<?php
namespace RKA\ContentTypeRenderer;

use Crell\ApiProblem\ApiProblem;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

class ApiProblemRenderer extends Renderer
{
    protected $defaultMediaType = null;

    protected $knownMediaTypes = [
        'application/problem+json',
        'application/problem+xml',
    ];

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
        // Look for API Problem specific media types first. If none, then find preferred format
        $mediaType = $this->determineMediaType($request->getHeaderLine('Accept'));
        if ($mediaType) {
            list ($_, $format) = explode('+', $mediaType);
        } else {
            $format = $this->determinePeferredFormat($request->getHeaderLine('Accept'), ['json', 'xml'], 'json');
        }


        // set the ProblemAPi content type for JSON or XML
        $output = $this->renderOutput($format, $problem);
        $contentType = 'application/problem+' . $format;
        
        $response = $this->writeBody($response, $output);
        $response = $response->withHeader('Content-type', $contentType);

        if ($problem->getStatus() >= 100) {
            $response = $response->withStatus($problem->getStatus());
        }
        
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
