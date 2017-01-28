<?php
namespace RKA\ContentTypeRenderer;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Nocarrier\Hal;
use RuntimeException;

class HalRenderer extends Renderer
{
    /**
     * Pretty print JSON (default true)
     * @var bool
     */
    protected $pretty;

    protected $defaultMediaType = null;

    protected $knownMediaTypes = [
        'application/hal+json',
        'application/hal+xml',
    ];

    public function __construct($pretty = true)
    {
        $this->pretty = (bool)$pretty;
    }

    public function render(RequestInterface $request, ResponseInterface $response, $data)
    {
        // Look for HAL specific media types first. If none, then find preferred format
        $mediaType = $this->determineMediaType($request->getHeaderLine('Accept'));
        if ($mediaType) {
            list ($_, $format) = explode('+', $mediaType);
        } else {
            $format = $this->determinePeferredFormat(
                $request->getHeaderLine('Accept'),
                ['json', 'xml', 'html'],
                'json'
            );
        }

        $output = $this->renderOutput($format, $data);
        if ($format == 'html') {
            $contentType = 'text/html';
        } else {
            $contentType = 'application/hal+' . $format;
        }

        $response = $this->writeBody($response, $output);
        $response = $response->withHeader('Content-type', $contentType);
        
        return $response;
    }

    protected function renderOutput($format, $data)
    {
        if (!$data instanceof Hal) {
            throw new RuntimeException('Data is not a Hal object');
        }

        switch ($format) {
            case 'html':
                $data = json_decode($data->asJson(), true);
                $output = $this->renderHtml($data);
                break;

            case 'xml':
                $output = $data->asXml();
                break;

            case 'json':
                $output = $data->asJson($this->pretty);
                break;
            
            default:
                throw new RuntimeException("Unknown content type $contentType");
        }

        return $output;
    }
}
