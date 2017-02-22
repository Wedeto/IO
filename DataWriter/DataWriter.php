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

abstract class DataWriter
{
    protected $pretty_print;

    public function __construct(bool $pprint = false)
    {
        $this->setPrettyPrint($pprint);
    }

    public function setPrettyPrint(bool $pprint)
    {
        $this->pretty_print = $pprint;
    }

    public function getPrettyPrint()
    {
        return $this->pretty_print;
    }

    public function write($data, $file_handle === null)
    {
        if (!is_array_like($data))
            throw new InvalidArgumentException("Data should be array or array-like");

        if ($file_handle === null)
        {
            $file_handle = fopen('php://memory', 'rw');
            $opened = true;
            $start_pos = 0;
        }
        else
        {
            if (!is_resource($file_handle))
                throw new InvalidArgumentException("File handle should be a resource of an opened file");

            $opened = false;
            $start_pos = ftell($file_handle);
        }

        $this->format($data, $file_handle);

        $length = ftell($file_handle);
        if ($opened)
        {
            fseek($this->file_handle, 0);
            $formatted = fread($this->file_handle, $length);
            fclose($this->file_handle);

            return $formatted;
        }

        return $length - $start_pos;
    }

    abstract protected function format($data, $file_handle);
}
