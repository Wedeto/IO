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

namespace WASP\IO;

class File
{
    private $dir;
    private $path;
    private $filename;
    private $basename;
    private $ext;
    private $mime;

    public function __construct(string $filename, $mime = null)
    {
        self::getLogger();
        $this->path = $filename;
        $this->dir = dirname($filename);
        if ($this->dir == ".")
            $this->dir = "";

        $this->filename = $filename = basename($filename);
        if (!empty($mime))
            $this->mime = $mime;
        $extpos = strrpos($filename, ".");

        if ($extpos !== false)
        {
            $this->ext = strtolower(substr($filename, $extpos + 1));
            $this->basename = substr($filename, 0, $extpos);
        }
        else
        {
            $this->basename = $this->filename;
            $this->ext = null;
        }
    }

    /**
     * Touch the file, updating its permissions
     */
    public function touch()
    {
        // Check permissions
        if (file_exists($this->path))
        {
            if (!is_writable($this->path))
                Path::makeWritable($this->path);
        }

        touch($this->path);
        Path::setPermissions($this->path);
    }

    /**
     * @return string The file extension
     */
    public function getExt()
    {
        return $this->ext;
    }

    /**
     * @return string the file name with a different file extension
     */
    public function setExt($ext)
    {
        if ($this->dir)
            return $this->dir . "/" . $this->basename . "." . $ext;
        return $this->basename . "." . $ext;
    }

    /**
     * Return the appropriate mime type for the file
     */
    public function getMime()
    {
        if (!$this->mime)
        {
            $mime = null;
            if ($this->ext === "css")
                $mime = "text/css";
            elseif ($this->ext == "json")
                $mime = "application/json";
            elseif ($this->ext == "js")
                $mime = "application/javascript";

            $mime = mime_content_type($this->path . "/" . $this->filename);
            $this->mime = $mime;
        }
        return $this->mime;
    }

    /**
     * @return string the path to this file
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @return string The file name with a suffix added before the extension
     */
    public function addSuffix($suffix)
    {
        if ($this->dir)
            return $this->dir . "/" . $this->basename . $suffix . "." . $this->ext;
        return $this->basename . $suffix . "." . $this->ext;
    }

    /**
     * @return string the filename without the directory
     */
    public function getFilename()
    {
        return $this->filename; 
    }

    /**
     * @return string The directory containing the file
     */
    public function getDir()
    {
        return $this->dir;
    }
    
    /**
     * @return string The filename without the extension
     */
    public function getBaseName()
    {
        return $this->basename;
    }

    /**
     * Set the permissions to the default values
     */
    public function setPermissions()
    {
        Path::setPermissions($this->path);
    }
}
