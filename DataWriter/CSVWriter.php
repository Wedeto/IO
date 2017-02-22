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

use WASP\is_array_like;
use WASP\cast_array;

class CSVWriter extends DataWriter
{
    protected $delimiter = ',';
    protected $enclosure = '"';
    protected $escape_char = '\\';
    protected $print_header = true;

    public function setDelimiter($delimiter)
    {
        $this->delimiter = $delimiter;
        return $this;
    }

    public function getDelimiter()
    {
        return $this->delimiter;
    }

    public function setEnclosure($enclosure)
    {
        $this->enclosure = $enclosure;
        return $this;
    }

    public function getEnclosure()
    {
        return $this->enclosure;
    }

    public function setEscapeChar(string $escape)
    {
        $this->escape_char = $escape;
        return $this;
    }

    public function getEscapeChar()
    {
        return $this->escape_char;
    }

    public function setPrintHeader(bool $print_header)
    {
        $this->print_header = $print_header;
    }

    public function getPrintHeader()
    {
        return $this->print_header;
    }

    /**
     * Format the data into CSV
     * @param mixed $data Traversable data
     */
    protected function format($data, $file_handle)
    {
        $header = false;
        foreach ($data as $idx => $row)
        {
            $row = cast_array($row);
            $this->validateRow($row);

            if (!$header && $this->print_header)
            {
                $keys = array_keys($row);
                fputcsv($file_handle, $keys, $this->delimiter, $this->enclosure, $this->escape_char);
                $header = true;
            }
            fputcsv($file_handle, $row, $this->delimiter, $this->enclosure, $this->escape_char);
        }
    }

    /**
     * Make sure that the data is not nested more than 1 level deep as CSV does not support that.
     * @param array $row The row to validate
     * @throws InvalidArgumentException When the array contains arrays
     */
    protected function validateRow(array $row)
    {
        foreach ($row as $k => $v)
        {
            if (is_array_like($v))
                throw new InvalidArgumentException("CSVWriter does not support nested arrays");
        }
    }
}
