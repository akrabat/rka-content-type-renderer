<?php
namespace RKA\ContentTypeRenderer;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Body;
use RuntimeException;
use LSS\Array2XML;

class Renderer
{
    protected $htmlPrefix;
    protected $htmlPostfix;

    public function render(RequestInterface $request, ResponseInterface $response, array $data)
    {
        $contentType = $this->determineContentType($request->getHeaderLine('Accept'));

        switch ($contentType) {
            case 'text/html':
                $output = $this->renderHtml($data);
                break;

            case 'application/xml':
                $xml = Array2XML::createXML('root', $data);
                $output = $xml->saveXML();
                break;

            case 'application/json':
            default:
                $contentType = 'application/json';
                $output = json_encode($data);
                break;
        }

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

        return $response
                ->withHeader('Content-type', $contentType)
                ->withBody($body);
    }

    /**
     * Render Array as HTML (thanks to joind.in's -api project!)
     *
     * This code is cribbed from https://github.com/joindin/joindin-api/blob/master/src/views/HtmlView.php
     *
     * @return string
     */
    private function renderHtml($data)
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
    private function arrayToHtml(array $content, $html = '')
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
    private function determineContentType($acceptHeader)
    {
        $list = explode(',', $acceptHeader);
        $known = ['application/json', 'application/xml', 'text/html'];
        
        foreach ($list as $type) {
            if (in_array($type, $known)) {
                return $type;
            }
        }

        return 'text/html';
    }

    /**
     * Getter for htmlPrefix
     *
     * @return mixed
     */
    public function getHtmlPrefix()
    {
        if (!$this->htmlPrefix) {
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
        if (!$this->htmlPostfix) {
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
