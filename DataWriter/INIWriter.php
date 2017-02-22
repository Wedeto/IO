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

/**
 * The INI-file class writes INI-files. If the INI-file exists,
 * it will be read to extract the comments and re-adds these comments
 * to the same section in the output file
 */
class INIWriter extends DataWriter
{
    protected function format($data, $file_handle)
    {
        // First read current data from file
        $pos = ftell($file_handle);
        $contents = "";
        while (!feof($file_handle))
            $contents .= fread($file_handle);
        fseek($file_handle, $pos);
        
        // Attempt to write config without removing comments
        $lines = explode("\n", $contents);
        $new_contents = "";
        
        $section = null;
        $leading = true;
        $section_comments = array();

        // Parse comments from existing file
        foreach ($lines as $line)
        {
            $line = trim($line);
            if (empty($line))
                continue;

            // Match sections
            if (preg_match("/^\[(.+)\]$/", $line, $matches))
            {
                $leading = false;
                if (!isset($data[$matches[1]]))
                {
                    // Skip this section
                    $section = null;
                    continue;
                }

                $section = $matches[1];
                $section_comments[$section] = array();
                continue;
            }
        
            // Don't remove comments
            if (substr($line, 0, 1) == ";" && ($section !== null || $leading = true))
            {
                if ($leading)
                    $section_comments[0][] = $line;
                else
                    $section_comments[$section][] = $line;
                continue;
            }
        }

        // Write config, re-add comments from original file
        $first = true;
        if (!empty($section_comments[0]))
        {
            $first = false;
            foreach ($section_comments[0] as $comment)
                fwrite($file_handle, $comment . "\n");
        }

        foreach ($data as $section => $parameters)
        {
            if ($first)
                $first = false;
            else
                $new_contents .= "\n";

            $parameters = to_array($parameters);

            fwrite($file_handle, "[" . $section . "]\n");
            $comments = isset($section_comments[$section]) ? $section_comments[$section] : array();
            sort($comments, SORT_STRING);
            ksort($parameters, SORT_STRING);

            $lines = array_merge($comments, $parameters);

            foreach ($lines as $name => $line)
            {
                if (is_string($line) && substr($line, 0, 1) == ";")
                    fwrite($file_handle, $line . "\n");
                else
                    self::writeParameter($file_handle, $name, $line);
            }
        }
    }

    /**
     * Recursive function that writes a parameter or a series of parameters
     * to the INI file
     *
     * @param $name string The name of the parameter
     * @param $parameter mixed The parameter to write
     */
    private static function writeParameter($file_handle, $name, $parameter, $depth = 0)
    {
        if ($depth > 1)
            throw new \DomainException("Cannot nest arrays more than once in INI-file");

        $str = "";
        if (is_array_like($parameter))
        {
            foreach ($parameter as $key => $val)
            {
                $prefix = $name . "[" . $key . "]";
                self::writeParameter($file_handle, $prefix, $val, $depth + 1); 
            }
        }
        elseif (is_bool($parameter))
            fwrite($file_handle, "$name = " . ($parameter ? "true" : "false") . "\n");
        elseif (is_null($parameter))
            fwrite($file_handle, "$name = null\n");
        elseif (is_float($parameter) && is_int_val((string)$parameter))
            fwrite($file_handle, "$name = " . sprintf("%.1f", $parameter) . "\n");
        elseif (is_float($parameter))
            fwrite($file_handle, "$name = " . $parameter . "\n");
        elseif (is_numeric($parameter))
            fwrite($file_handle, "$name = " . $parameter . "\n");
        else
            fwrite($file_handle, "$name = \"" . str_replace('"', '\\"', $parameter) . "\"\n");
    }
}
