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

use WASP\IOException;
use Iterator;

/**
 * Read CSV files. This provides a direct CSV reader that converts a CSV file
 * directly to an array of records, but alternatively, you can traverse the
 * file record by record, by opening the file in the constructor and traversing
 * using foreach. This allows to handle large files without loading everything
 * into memory.
 */
class CSVReader extends DataReader implements Iterator
{
    protected $file_handle;
    protected $line_number;
    protected $current_line = null;

    protected $delimiter = ',';
    protected $enclosure = '"';
    protected $escape_char = '\\';
    protected $read_header = true;

    protected $header = null;

    public function __construct($file_name = null)
    {
        if ($file_name !== null)
            $this->file_handle = fopen($file_name, "r");
    }

    public function setDelimiter(string $delimiter)
    {
        $this->delimiter = $delimiter;
        return $this;
    }

    public function getDelimiter()
    {
        return $this->delimiter;
    }

    public function setEnclosure(string $enclosure)
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

    public function setReaderHeader(bool $read_header)
    {
        $this->read_header = $read_header;
    }

    public function getReadHeader()
    {
        return $this->read_header;
    }

    public function readFile(string $file_name)
    {
        $data = array();
        $this->file_handle = fopen($file_name, "r");

        foreach ($this as $row)
            $data[] = $row;

        return $data;
    }

    public function readFileHandle($file_handle)
    {
        if (!is_resource($file_handle))
            throw new \InvalidArgumentException("No file handle was provided");

        $data = array();
        $this->file_handle = $file_handle;

        foreach ($this as $row)
            $data[] = $row;

        return $data;
    }

    public function readString(string $data)
    {
        $buf = fopen('php://memory', 'rw');
        fwrite($buf, $data);

        $data = array();
        foreach ($this as $row)
            $data[] = $row;

        return $data;
    }

    public function rewind()
    {
        fseek($this->file_handle, 0);
        $this->line_number = 0;
        $this->current_line = null;
        $this->header = null;
    }

    public function current()
    {
        if ($this->current_line === false)
            return false;

        if ($this->current_line === null)
            $this->readLine();

        return $this->current_line;
    }

    public function key()
    {
        return $this->line_number;
    }

    public function next()
    {
        $this->current_line = null;
        ++$this->line_number;
    }

    public function valid()
    {
        if ($this->current_line === null)
            $this->readLine();

        return $this->current_line !== false;
    }
    
    protected function readLine()
    {
        if ($this->read_header && $this->line_number === 0 && $this->header === null)
            $this->header = fgetcsv($this->file_handle, 0, $this->delimiter, $this->enclosure, $this->escape_char);

        $line = fgetcsv($this->file_handle, 0, $this->delimiter, $this->enclosure, $this->escape_char);

        if ($line === false)
        {
            $this->current_line = false;
            return;
        }

        if ($this->header !== null)
        {
            $row = array();
            foreach ($line as $idx => $col)
            {
                $name = isset($this->header[$idx]) ? $this->header[$idx] : null;
                if ($name)
                    $row[$name] = $col;
                else
                    $row[] = $col;
            }
            $this->current_line = $row;
        }
        else
            $this->current_line = $line;
    }
}
