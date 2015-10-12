<?php
namespace RKA\ContentTypeRenderer;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Nocarrier\Hal;
use RuntimeException;

class HalRenderer extends Renderer
{
    protected $knownContentTypes = [
        'application/hal+json',
        'application/hal+xml',
        'application/json',
        'application/xml',
        'text/xml',
        'text/html'
    ];

    public function render(RequestInterface $request, ResponseInterface $response, $data)
    {
        $contentType = $this->determineContentType($request->getHeaderLine('Accept'));

        $output = $this->renderOutput($contentType, $data);
        $response = $this->writeBody($response, $output);

        // set the HAL content type for JSON or XML
        if (stripos($contentType, 'json')) {
            $contentType = 'application/hal+json';
        } elseif (stripos($contentType, 'xml')) {
            $contentType = 'application/hal+xml';
        }
        $response = $response->withHeader('Content-type', $contentType);
        
        return $response;
    }

    protected function renderOutput($contentType, $data)
    {
        if (!$data instanceof Hal) {
            throw new RuntimeException('Data is not a Hal object');
        }

        switch ($contentType) {
            case 'text/html':
                $data = json_decode($data->asJson(), true);
                $output = $this->renderHtml($data);
                break;

            case 'application/hal+xml':
            case 'application/xml':
            case 'text/xml':
                $output = $data->asXml();
                break;

            case 'application/hal+json':
            case 'application/json':
                $output = $data->asJson();
                break;
            
            default:
                throw new RuntimeException("Unknown content type $contentType");
        }

        return $output;
    }
}
