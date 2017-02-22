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

use WASP\Debug\Logger;

class XMLWriter extends DataWriter
{
    private $root_node = "Response";

    public function setRootNode(string $node_name)
    {
        $this->root_node = $node_name;
        return $this;
    }

    public function getRootNode()
    {
        return $this->root_node;
    }

    protected function format($data, $file_handle)
    {
        $writer = \XMLWriter::openMemory();
        $writer->startDocument();

        $this->startElement($this->root_node);
        $this->writeXMLRecursive($writer, $data);
        $this->endElement();
        
        $writer->endDocument();
        fwrite($file_handle, $writer->outputMemory());
    }

    protected function formatRecursive(\XMLWriter $writer, $data)
    {
        foreach ($data as $key => $value)
        {
            if (substr($key, 0, 1) == "_")
            {
                $writer->writeAttribute(substr($key, 1), (string)$value); 
            }
            else
            {
                $writer->startElement($key);
                if (is_array($value))
                    $this->formatRecursive($writer, $value);
                else
                    $this->text(Logger::str($value));
                $writer->endElement();
            }
        }
    }

}
