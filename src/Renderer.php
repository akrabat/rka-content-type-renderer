<?php
namespace RKA\ContentTypeRenderer;

use Negotiation\Exception\InvalidMediaType;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Slim\Http\Body;

class Renderer
{
    /**
     * Pretty print JSON (default true)
     * @var bool
     */
    protected $pretty;

    protected $defaultMediaType = 'application/json';
    protected $knownMediaTypes = ['application/json', 'application/xml', 'text/xml', 'text/html'];
    protected $mediaSubtypesToAllowedDataTypesMap = [
        'xml' => ['array', 'JsonSerializable'],
        'json' => ['scalar','array', 'JsonSerializable'],
        'html' => ['scalar','array', 'JsonSerializable']
    ];
    protected $xmlRootElementName = 'root';
    protected $htmlPrefix;
    protected $htmlPostfix;

    public function __construct($pretty = true)
    {
        $this->pretty = (bool)$pretty;
    }

    public function render(RequestInterface $request, ResponseInterface $response, $data)
    {
        $mediaType = $this->determineMediaType($request->getHeaderLine('Accept'));

        $mediaSubType = explode('/', $mediaType)[1];
        $dataIsValidForMediatype = $this->isDataValidForMediaType($mediaSubType, $data);
        if (!$dataIsValidForMediatype) {
            throw new RuntimeException('Data for mediaType ' . $mediaType . ' must be '
                . implode($this->mediaSubtypesToAllowedDataTypesMap[$mediaSubType], ' or '));
        }

        $output = $this->renderOutput($mediaType, $data);
        $response = $this->writeBody($response, $output);
        $response = $response->withHeader('Content-type', $mediaType);

        return $response;
    }

    protected function isDataValidForMediaType($mediaSubType, $data)
    {
        $allwedDataTypes = $this->mediaSubtypesToAllowedDataTypesMap[$mediaSubType];

        foreach ($allwedDataTypes as $allowedDataType) {
            switch ($allowedDataType) {
                case 'scalar':
                    if (is_scalar($data)) {
                        return true;
                    }
                    break;
                case 'array':
                    if (is_array($data)) {
                        return true;
                    }
                    break;
                case 'JsonSerializable':
                    if ($data instanceof \JsonSerializable) {
                        return true;
                    }
                    break;
            }
        }

        return false;
    }

    protected function renderOutput($mediaType, $data)
    {
        switch ($mediaType) {
            case 'text/html':
                $data = json_decode(json_encode($data), true);
                $output = $this->renderHtml($data);
                break;

            case 'application/xml':
            case 'text/xml':
                $data = json_decode(json_encode($data), true);
                $output = $this->renderXml($data);
                break;

            case 'application/json':
                $options = 0;
                if ($this->pretty) {
                    $options = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES;
                }
                $output = json_encode($data, $options);
                break;

            default:
                throw new RuntimeException("Unknown media type $mediaType");
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
     * @param mixed $content data to be rendered
     *
     * @return null
     */
    protected function arrayToHtml($content, $html = '')
    {
        // scalar types can be return directly
        if (is_scalar($content)) {
            return $content;
        }

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
     * Read the accept header and determine which media type we know about
     * is wanted.
     *
     * @param  string $acceptHeader Accept header from request
     * @return string
     */
    protected function determineMediaType($acceptHeader)
    {
        if (!empty($acceptHeader)) {
            $negotiator = new Negotiator();
            $mediaType = $negotiator->getBest($acceptHeader, $this->knownMediaTypes);

            if ($mediaType) {
                return $mediaType->getValue();
            }
        }

        return $this->getDefaultMediaType();
    }

    /**
     * Read the accept header and work out which format is preferred
     *
     * @param  string $acceptHeader Accept header from request
     * @param  array  $allowedFormats Array of formats that are preferred
     * @param  string $default Default format to return if no allowedFormats are found
     * @return string
     */
    public function determinePeferredFormat($acceptHeader, $allowedFormats = ['json', 'xml', 'html'], $default = 'json')
    {
        if (empty($acceptHeader)) {
            return $default;
        }

        $negotiator = new Negotiator();
        try {
            $elements = $negotiator->getOrderedElements($acceptHeader);
        } catch (InvalidMediaType $e) {
            return $default;
        }

        foreach ($elements as $element) {
            $subpart = $element->getSubPart();
            foreach ($allowedFormats as $format) {
                if (stripos($subpart, $format) !== false) {
                    return $format;
                }
            }
        }

        return $default;
    }

    /**
     * Getter for defaultMediaType
     *
     * @return string
     */
    public function getDefaultMediaType()
    {
        return $this->defaultMediaType;
    }

    /**
     * Setter for defaultMediaType
     *
     * @param string $defaultMediaType Value to set
     * @return self
     */
    public function setDefaultMediaType($defaultMediaType)
    {
        $this->defaultMediaType = $defaultMediaType;
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

    /**
     * Getter for xmlRootElementName
     *
     * @return string
     */
    public function getXmlRootElementName()
    {
        return $this->xmlRootElementName;
    }

    /**
     * Setter for xmlRootElementName
     *
     * @param string $xmlRootElementName
     * @return self
     */
    public function setXmlRootElementName($xmlRootElementName)
    {
        $this->xmlRootElementName = $xmlRootElementName;
        return $this;
    }

    /**
     * Render Array as XML
     *
     * @return string
     */
    protected function renderXml($data)
    {
        $xml = $this->arrayToXml($data);

        $dom = new \DOMDocument('1.0');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = $this->pretty;
        $dom->loadXML($xml->asXML());

        return $dom->saveXML();
    }

    /**
     * Simple Array to XML conversion
     * Based on http://www.codeproject.com/Questions/553031/JSONplusTOplusXMLplusconvertionpluswithplusphp
     *
     * @param  array $data                  Data to convert
     * @param  SimpleXMLElement $xmlElement XMLElement
     * @return SimpleXMLElement
     */
    protected function arrayToXml($data, $xmlElement = null)
    {
        if ($xmlElement === null) {
            $rootElementName = $this->getXmlRootElementName();
            $xmlElement = new \SimpleXMLElement("<?xml version=\"1.0\"?><$rootElementName></$rootElementName>");
        }

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if (!is_numeric($key)) {
                    $subnode = $xmlElement->addChild("$key");

                    if (count($value) > 1 && is_array($value)) {
                        $jump = false;
                        $count = 1;
                        foreach ($value as $k => $v) {
                            if (is_array($v)) {
                                if ($count++ > 1) {
                                    $subnode = $xmlElement->addChild("$key");
                                }

                                $this->arrayToXml($v, $subnode);
                                $jump = true;
                            }
                        }
                        if ($jump) {
                            goto LE;
                        }
                        $this->arrayToXml($value, $subnode);
                    } else {
                        $this->arrayToXml($value, $subnode);
                    }
                } else {
                    $this->arrayToXml($value, $xmlElement);
                }
            } else {
                if (is_bool($value)) {
                    $value = (int)$value;
                }
                if (is_numeric($key)) {
                    $key = "_$key";
                }
                $xmlElement->addChild("$key", "$value");
            }

            LE: ;
        }

        return $xmlElement;
    }
}
