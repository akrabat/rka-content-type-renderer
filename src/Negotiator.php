<?php
namespace RKA\ContentTypeRenderer;

use Negotiation\Exception\InvalidArgument;
use Negotiation\Negotiator as BaseNegotiator;

/**
 * Our own extension to Negotiator to add getOrderedElements()
 */
class Negotiator extends BaseNegotiator
{
    /**
     * Direct copy of AbstractNegotiator::parseHeader() as it's private
     *
     * @codeCoverageIgnore
     *
     * @param string $header A string that contains an `Accept*` header.
     *
     * @return AcceptHeader[]
     */
    protected function parseHeader($header)
    {
        $res = preg_match_all('/(?:[^,"]*+(?:"[^"]*+")?)+[^,"]*+/', $header, $matches);

        if (!$res) {
            throw new InvalidHeader(sprintf('Failed to parse accept header: "%s"', $header));
        }

        return array_values(array_filter(array_map('trim', $matches[0])));
    }

    /**
     * Order the elements of the Accept header by quality/position
     *
     * @param string $header  A string containing an `Accept|Accept-*` header.
     *
     * @return [AcceptHeader] An ordered list of accept header elements
     */
    public function getOrderedElements($header)
    {
        if (!$header) {
            throw new InvalidArgument('The header string should not be empty.');
        }

        $elements = array();
        $orderKeys = array();
        foreach ($this->parseHeader($header) as $key => $h) {
            try {
                $element = $this->acceptFactory($h);
                $elements[] = $element;
                $orderKeys[] = [$element->getQuality(), $key, $element->getValue()];
            } catch (Exception\Exception $e) {
                // silently skip in case of invalid headers coming in from a client
            }
        }
        
        // sort based on quality and then original order. This is necessary as
        // to ensure that the first in the list for two items with the same
        // quality stays in that order in both PHP5 and PHP7.
        uasort($orderKeys, function ($a, $b) {
            $qA = $a[0];
            $qB = $b[0];
            
            if ($qA == $qB) {
                return $a[1] > $b[1];
            }
            
            return ($qA > $qB) ? -1 : 1;
        });

        $orderedElements = [];
        foreach ($orderKeys as $key) {
            $orderedElements[] = $elements[$key[1]];
        }

        return $orderedElements;
    }
}
