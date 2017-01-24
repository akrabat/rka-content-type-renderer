<?php
namespace RKA\ContentTypeRenderer\Tests;

use RKA\ContentTypeRenderer\Negotiator;
use Negotiation\Exception\InvalidArgument;
use Negotiation\Exception\InvalidMediaType;

class NegotiatorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Negotiator
     */
    private $negotiator;

    protected function setUp()
    {
        $this->negotiator = new Negotiator();
    }

    public static function dataProviderForTestGetOrderedElements()
    {
        return array(
            // error cases
            array('', new InvalidArgument('The header string should not be empty.')),
            array('/qwer', new InvalidMediaType()),

            // first one wins as no quality modifiers
            array('text/html, text/xml', array('text/html', 'text/xml')),
            
            // ordered by quality modifier
            array(
                'text/html;q=0.3, text/html;q=0.7',
                array('text/html;q=0.7', 'text/html;q=0.3')
            ),
            // ordered by quality modifier - the one with no modifier wins, level not taken into account
            array(
                'text/*;q=0.3, text/html;q=0.7, text/html;level=1, text/html;level=2;q=0.4, */*;q=0.5',
                array('text/html;level=1', 'text/html;q=0.7', '*/*;q=0.5', 'text/html;level=2;q=0.4', 'text/*;q=0.3')
            ),
        );
    }

    /**
     * @dataProvider dataProviderForTestGetOrderedElements
     */
    public function testGetOrderedElements($header, $expected)
    {
        try {
            $elements = $this->negotiator->getOrderedElements($header);
        } catch (\Exception $e) {
            $this->assertEquals($expected, $e);
            return;
        }

        if (empty($elements)) {
            $this->assertNull($expected);
            return;
        }

        $this->assertInstanceOf('Negotiation\Accept', $elements[0]);

        foreach ($expected as $key => $item) {
            $this->assertSame($item, $elements[$key]->getValue());
        }
    }
}
