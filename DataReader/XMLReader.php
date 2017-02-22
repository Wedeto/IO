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

use XMLReader as PHPXMLReader;
use WASP\IOException;

class XMLReader extends DataReader
{
    public function readFile(string $file_name)
    {
        $reader = new PHPXMLReader;
        $reader->open($file_name);

        $data = $this->toArray($reader);
        $reader->close();

        return $data;
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
        $reader = new PHPXMLReader;
        $reader->XML($data);

        $contents = $this->toArray($reader);
        $reader->close();

        return $contents;
    }

    public function toArray(PHPXMLReader $reader)
    {
        $data = array();

        $root = new XMLNode;
        $cur = null;

        while ($reader->read())
        {
            if ($reader->nodeType === PHPXMLReader::ELEMENT)
            {
                if ($parent === null)
                {
                    $root->name = $reader->name;
                    $cur = $root;
                }
                else
                {
                    $node = new XMLNode;
                    $node->name = $reader->name;
                    $node->parent = $parent;
                    $cur->children[] = $node;
                    $cur = $node;
                }

                if ($reader->hasAttributes)
                {
                    $attributes = array();
                    while ($reader->moveToNextAttribute())
                    {
                        $node = new XMLNode;
                        $node->name = "_" . $reader->name;
                        $node->content = $reader->value;
                        $cur->children[] = $node;
                    }
                }

            }
            else if ($reader->nodeType === PHPXMLReader::END_ELEMENT)
            {
                $cur = $cur->parent;
            }
            else if ($reader->nodeType === PHPXMLReader::TEXT)
            {
                $cur->content = $reader->value;
            }
        }

        if ($cur !== null)
            throw new \RuntimeException("Invalid XML");
        return $root->JSONSerialize();
    }
}

class XMLNode implements JSONSerializable
{
    public $name = null;
    public $parent = null;
    public $children = array();
    public $content = null;

    public function JSONSerialize()
    {
        $children = array();
        foreach ($this->children as $child)
        {
            if (!isset($children[$child->name]))
                $children[$child->name] = array();
            $children[$child->name][] = $child->JSONSerialize();
        }

        $keys = array_keys($children);
        foreach ($keys as $key)
        {
            if (count($children[$key]) === 1)
                $children[$key] = $children[$key][0];
        }
        return $children;
    }
}
