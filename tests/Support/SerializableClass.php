<?php
namespace RKA\ContentTypeRenderer\Tests\Support;

class SerializableClass implements \JsonSerializable
{
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function jsonSerialize()
    {
        return $this->data;
    }
}
