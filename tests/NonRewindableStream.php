<?php
namespace RKA\ContentTypeRenderer\Tests;

/**
 * Stream wrapper that doesn't rewind a writable stream
 *
 * Based in large part on the example at
 * http://php.net/manual/en/stream.streamwrapper.example-1.php
 *
 */
class NonRewindableStream
{
    public function stream_open($path, $mode, $options, &$opened_path)
    {
        return true;
    }

    public function stream_eof()
    {
        return true;
    }

    public function stream_seek($offset, $whence)
    {
        return false;
    }
}
