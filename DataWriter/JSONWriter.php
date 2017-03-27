<?php
/*
This is part of WASP, the Web Application Software Platform.
It is published under the MIT Open Source License.

Copyright 2017, Egbert van der Wal

Permission is hereby granted, free of charge, to any person obtaining a copy of
this software and associated documentation files (the "Software"), to deal in
the Software without restriction, including without limitation the rights to
use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of
the Software, and to permit persons to whom the Software is furnished to do so,
subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS
FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/

namespace WASP\IO\DataWriter;

use WASP\Util\Encoding;
use WASP\Util\Functions as WF;

class JSONWriter extends DataWriter
{
    protected $json_callback = null;

    public function format($data, $file_handle)
    {
        // Start by invoking JSON-callback when enabled
        if (!empty($this->json_callback))
            fwrite($file_handle, $this->json_callback . '(');

        // Write data
        if ($this->pretty_print)
            self::pprintJSON($data, 0, null, $file_handle);
        else
            self::writeJSON($data, $file_handle);

        // Close JSONP output when enabled
        if (!empty($this->json_callback))
            fwrite($file_handle, ');');
    }

    /**
     * Set a JSONP callback function
     *
     * @param string $callback The callback function
     * @return WASP\IO\DataWriter\JSONWriter Provides fluent interface
     */
    public function setCallback(string $callback)
    {
        $this->json_callback = $callback;
        return $this;
    }

    /**
     * @return string The mime type for the response
     */
    public function getMimeType()
    {
        return empty($this->json_callback) ? "application/json" : "application/javascript";
    }

    /**
     * Encode the specified object, catching UTF8 errors.
     *
     * @param JSONSerializable $obj The data to output
     * @param resource $buf The buffer to write to. If this is specified, nothing is returned.
     * @return string The JSON encoded data if no buffer was specified.
     */
    public static function writeJSON($obj, $buf = null)
    {
        $output = json_encode($obj);

        if ($output === false && json_last_error() === JSON_ERROR_UTF8)
        {
            $obj_fixed = Encoding::fixUTF8($obj);
            $output = json_encode($obj_fixed);
            if ($output === false && json_last_error() === JSON_ERROR_UTF8)
            {
                // @codeCoverageIgnoreStart
                // The input has to be extremely weird to trigger this, so
                // in practice it shouldn't happen.  
                $rep = WF::str($obj); 
                throw new IOException("Invalid encoding: " . $rep); 
                // @codeCoverageIgnoreEnd
            }
        }

        if (is_resource($buf))
        {
            fwrite($buf, $output);
            return "";
        }

        return $output;
    }

    /**
     * PrettyPrint the specified output.
     * 
     * @param JSONSerializable $obj The data to output
     * @param bool $json_array When set to true, output will be formatted as a
     *                         JSON array, rather than an object. If this is set to null
     *                         the keys will be examined to auto-detect the proper value.
     * @param resource $buf The buffer to write to. If this is specified,
     *                      nothing is returned.
     * @return string the JSON encoded, formatted data, if no buffer is
     *                specified.
     */
    public static function pprintJSON($obj, $indent = 0, $json_array = null, $buf = null)
    {
        if (is_object($obj) && method_exists($obj, "jsonSerialize"))
            $obj = $obj->jsonSerialize();

        if (!WF::is_array_like($obj))
            return self::writeJSON($obj, $buf);

        if ($json_array === null)
            $array = WF::is_numeric_array($obj);
        else
            $array = (bool)$json_array;

        // Open a memory buffer when no buffer is provided
        $return_buf = false;
        if ($buf === null)
        {
            $buf = fopen("php://memory", "rw");
            $return_buf = true;
        }

        // Write opening bracket
        fwrite($buf, $array ? "[\n" : "{\n");

        // Write properties
        $indent += 4;
        $tot = count($obj);
        $cur = 0;
        foreach ($obj as $key => $sub)
        {
            // Keys need to be string, otherwise they will not be quoted
            if (is_numeric($key) && !$array)
                $key = (string)$key;

            $ending = (++$cur < $tot ? ",\n" : "\n");
            fwrite($buf, str_repeat(' ', $indent));
            if (!$array)
            {
                self::writeJSON($key, $buf);
                fwrite($buf, ': ');
            } 

            if (is_array_like($sub) || is_object($sub))
                self::pprintJSON($sub, $indent, null, $buf);
            else
                self::writeJSON($sub, $buf);

            fwrite($buf, $ending);
        }

        // Write closing bracket
        fwrite($buf, str_repeat(' ', $indent - 4) . ($array ? ']' : '}'));

        if (!$return_buf)
            return;

        // Get buffer contents and return it
        rewind($buf);
        $json = stream_get_contents($buf);
        fclose($buf);
        return $json;
    }
}
