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

namespace WASP\IO\DataReader;

use ErrorException;

use WASP\Util\Functions as WF;
use WASP\IOException;

class YAMLReader extends DataReader
{
    public function readFile(string $file_name)
    {
        try
        {
            return yaml_parse_file($file_name);
        }
        catch (ErrorException $e)
        {
            throw new IOException($e);
        }
    }

    public function readFileHandle($file_handle)
    {
        if (!is_resource($file_handle))
            throw new \InvalidArgumentException("No file handle was provided");

        $contents = "";
        while (!feof($file_handle))
            $contents .= fread($file_handle, 8192);

        return $this->readString($contents);
    }

    public function readString(string $data)
    {
        try
        {
            return yaml_parse($data);
        }
        catch (ErrorException $e)
        {
            throw new IOException($e);
        }
    }
}

WF::check_extension('yaml', null, 'yaml_parse');
