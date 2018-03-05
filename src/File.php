<?php
/*
This is part of Wedeto, the WEb DEvelopment TOolkit.
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

namespace Wedeto\IO;

/**
 * A class providing information about a file. Subclasses SplFileInfo,
 * providing additional functions such as mime types, permission updates
 * and meaningful file open errors.
 */
class File extends \SplFileInfo
{
    protected $mime;

    public function __construct(string $filename, $mime = null)
    {
        parent::__construct($filename);
        if (!empty($mime))
            $this->mime = $mime;
    }

    /**
     * Touch the file, updating its permissions
     */
    public function touch()
    {
        // Check permissions
        $path = $this->getFullPath();
        if (file_exists($path))
        {
            if (!is_writable($path))
                Path::makeWritable($path);
        }

        touch($path);
        Path::setPermissions($path);
    }

    /**
     * @return string The file extension, lowercased
     */
    public function getExtension()
    {
        return strtolower(parent::getExtension());
    }

    /**
     * @return string the file name with a different file extension
     */
    public function withExtension($ext)
    {
        $dir = $this->getDir();
        $base = $this->getBasename();
        if (!empty($dir))
            return $dir . "/" . $base . "." . $ext;
        return $base . "." . $ext;
    }

    /**
     * Return the appropriate mime type for the file
     */
    public function getMime()
    {
        if (!$this->mime)
        {
            $type = FileType::getFromFile($this->getFullPath());
            $this->mime = $type->getMimeType();
        }
        return $this->mime;
    }

    /**
     * @return string The file name with a suffix added before the extension
     */
    public function withSuffix($suffix)
    {
        $file = $this->getBasename() . $suffix;
        $ext = $this->getExtension();
        if (!empty($ext))
            $file .= "." . $ext;

        $dir = $this->getDir();
        if (!empty($dir))
            return $dir . "/" . $file;

        return $file;
    }

    /**
     * Get the path to the file
     */
    public function getFullPath()
    {
        return $this->getPathname();
    }

    public function getBasename($suffix = null)
    {
        if ($suffix === null)
        {
            $ext = parent::getExtension();
            $suffix = !empty($ext) ? "." . $ext : null;
        }
        return parent::getBasename($suffix);
    }

    /**
     * @return string The directory containing the file
     */
    public function getDir()
    {
        return parent::getPath();
    }
    
    /**
     * Set the permissions to the default values
     */
    public function setPermissions()
    {
        Path::setPermissions($this->getFullPath());
    }

    /**
     * Open the file for reading or writing, throwing informative
     * exceptions when it fails.
     *
     * @param string $mode The file opening mode
     * @return resource The opened file resource
     * @throws IOException When opening the file failed.
     * @seealso fopen
     */
    public function open(string $mode)
    {
        $read = strpbrk($mode, "r+") !== false;
        $write = strpbrk($mode, "waxc+") !== false;
        $x = strpbrk($mode, "x") !== false;

        $path = $this->getFullPath();
        $fh = @fopen($path, $mode);
        if (is_resource($fh))
            return $fh;

        if ($x && file_exists($path))
            throw new IOException("File already exists: " . $path);
        if ($write && !is_writable($path))
            throw new IOException("File is not writable: " . $path);
        if ($read && !is_readable($path))
            throw new IOException("File is not readable: " . $path);

        throw new IOException("Invalid mode for opening file: " . $mode);
    }
}
