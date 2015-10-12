<?php
namespace RKA\ContentTypeRenderer;

use Nocarrier\Hal;
use RuntimeException;

class HalRenderer extends Renderer
{
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

            case 'application/xml':
            case 'text/xml':
                $output = $data->asXml();
                break;

            case 'application/json':
                $output = $data->asJson();
                break;
            
            default:
                throw new RuntimeException("Unknown content type $contentType");
        }

        return $output;
    }
}
