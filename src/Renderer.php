<?php
namespace RKA\ContentTypeRenderer;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Body;
use RuntimeException;

class Renderer
{
    protected $defaultContentType = 'application/json';
    protected $knownContentTypes = ['application/json', 'application/xml', 'text/xml', 'text/html'];
    protected $htmlPrefix;
    protected $htmlPostfix;

    public function render(RequestInterface $request, ResponseInterface $response, $data)
    {
        $contentType = $this->determineContentType($request->getHeaderLine('Accept'));

        $output = $this->renderOutput($contentType, $data);
        $response = $this->writeBody($response, $output);
        $response = $response->withHeader('Content-type', $contentType);
        
        return $response;
    }

    protected function renderOutput($contentType, $data)
    {
        if (!is_array($data)) {
            throw new RuntimeException('Data is not an array');
        }

        switch ($contentType) {
            case 'text/html':
                $output = $this->renderHtml($data);
                break;

            case 'application/xml':
            case 'text/xml':
                $xml = Array2XML::createXML('root', $data);
                $output = $xml->saveXML();
                break;

            case 'application/json':
                $output = json_encode($data);
                break;
            
            default:
                throw new RuntimeException("Unknown content type $contentType");
        }

        return $output;
    }

    protected function writeBody($response, $output)
    {
        $body = $response->getBody();
        if (!$body->isWritable()) {
            // the response's body is not writable (or doesn't exist)
            // so create our own
            $body = new SimplePsrStream(fopen('php://temp', 'r+'));
        }
        try {
            $body->rewind();
        } catch (\RuntimeException $e) {
            // could not rewind the stream, therefore use our own.
            $body = new SimplePsrStream(fopen('php://temp', 'r+'));
        }
        $body->write($output);

        return $response->withBody($body);
    }

    /**
     * Render Array as HTML (thanks to joind.in's -api project!)
     *
     * This code is cribbed from https://github.com/joindin/joindin-api/blob/master/src/views/HtmlView.php
     *
     * @return string
     */
    protected function renderHtml($data)
    {
        $html = $this->getHtmlPrefix();
        $html .= $this->arrayToHtml($data);
        $html .= $this->getHtmlPostfix();

        return $html;
    }

    /**
     * Recursively render an array to an HTML list
     *
     * @param array $content data to be rendered
     *
     * @return null
     */
    protected function arrayToHtml(array $content, $html = '')
    {
        $html = "<ul>\n";

        // field name
        foreach ($content as $field => $value) {
            $html .= "<li><strong>" . $field . ":</strong> ";
            if (is_array($value)) {
                // recurse
                $html .= $this->arrayToHtml($value);
            } else {
                // value, with hyperlinked hyperlinks
                if (is_bool($value)) {
                    $value = $value ? 'true' : 'false';
                }
                $value = htmlentities($value, ENT_COMPAT, 'UTF-8');
                if ((strpos($value, 'http://') === 0) || (strpos($value, 'https://') === 0)) {
                    $html .= "<a href=\"" . $value . "\">" . $value . "</a>";
                } else {
                    $html .= $value;
                }
            }
            $html .= "</li>\n";
        }
        $html .= "</ul>\n";

        return $html;
    }

    /**
     * Read the accept header and determine which content type we know about
     * is wanted.
     *
     * @param  string $acceptHeader Accept header from request
     * @return string
     */
    protected function determineContentType($acceptHeader)
    {
        $list = explode(',', $acceptHeader);
        
        
        foreach ($list as $type) {
            if (in_array($type, $this->knownContentTypes)) {
                return $type;
            }
        }

        return $this->getDefaultContentType();
    }

    /**
     * Getter for defaultContentType
     *
     * @return string
     */
    public function getDefaultContentType()
    {
        return $this->defaultContentType;
    }
    
    /**
     * Setter for defaultContentType
     *
     * @param string $defaultContentType Value to set
     * @return self
     */
    public function setDefaultContentType($defaultContentType)
    {
        $this->defaultContentType = $defaultContentType;
        return $this;
    }

    /**
     * Getter for htmlPrefix
     *
     * @return mixed
     */
    public function getHtmlPrefix()
    {
        if ($this->htmlPrefix === null) {
            $this->htmlPrefix = <<<HTML
<!DOCTYPE html>
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
<body>
HTML;
        }
        return $this->htmlPrefix;
    }
    
    /**
     * Setter for htmlPrefix
     *
     * @param mixed $htmlPrefix Value to set
     * @return self
     */
    public function setHtmlPrefix($htmlPrefix)
    {
        $this->htmlPrefix = $htmlPrefix;
        return $this;
    }
    
    /**
     * Getter for htmlPostfix
     *
     * @return mixed
     */
    public function getHtmlPostfix()
    {
        if ($this->htmlPostfix === null) {
            $this->htmlPostfix = <<<HTML
</body>
</html>

HTML;

        }
        return $this->htmlPostfix;
    }
    
    /**
     * Setter for htmlPostfix
     *
     * @param mixed $htmlPostfix Value to set
     * @return self
     */
    public function setHtmlPostfix($htmlPostfix)
    {
        $this->htmlPostfix = $htmlPostfix;
        return $this;
    }
}
