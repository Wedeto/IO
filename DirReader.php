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

use Iterator;

/**
 * Read directory contents
 */
class DirReader implements Iterator
{
    /** Read all contents of directory */
    const READ_ALL = 1;

    /** Read only files in the directory */
    const READ_FILE = 2;

    /** Read only subdirectories */
    const READ_DIR = 3;

    /** The path to the directory */
    private $path;

    /** The \Directory object */
    private $dir;
    
    /** The iterator position */
    private $iter = 0;

    /** The current entry */
    private $cur_entry = null;

    /** The next entry */
    private $next_entry = null;

    /** What to read: READ_ALL, READ_FILE, or READ_DIR */
    private $read_what;

    /**
     * Create a new instance of the directory reader
     * @param string $path The path to the directory
     * @param int $what What to read: DirReader::READ_ALL, DirReader::READ_FILE or DirReader::READ_DIR
     */
    public function __construct(string $path, int $what = DirReader::READ_ALL)
    {
        $this->path = realpath($path);
        $this->dir = \dir($this->path);
        $this->read_what = $what;
    }

    /**
     * @return string The next file or directory
     */
    public function next()
    {
        if ($this->next_entry === null)
            $this->hasNext();

        $this->cur_entry = $this->next_entry;
        $this->next_entry = null;
        ++$this->iter;
    }

    /** 
     * @return int The current iterator position
     */
    public function key()
    {
        return $this->iter;
    }

    /** 
     * @return string The current file or directory
     */
    public function current()
    {
        return $this->cur_entry;
    }

    /**
     * Rewind the directory reader to the start
     */
    public function rewind()
    {
        $this->dir->rewind();
        $this->iter = 0;
        $this->hasNext();
        $this->cur_entry = $this->next_entry;
        $this->next_entry = null;
    }

    /**
     * @return bool True if there are more entries, false otherwise
     */
    public function hasNext()
    {
        while ($this->next_entry === null)
        {
            $nv = $this->dir->read();
            if ($nv === false)
            {
                $this->next_entry = null;
                break;
            }

            if ($nv === "." || $nv === "..")
                continue;

            $path = $this->path . '/' . $nv;
            if ($this->read_what === DirReader::READ_DIR && !is_dir($path))
                continue;
            elseif ($this->read_what === DirReader::READ_FILE && !is_file($path))
                continue;

            $this->next_entry = $nv;
            break;
        }
        return !empty($this->next_entry);
    }

    /**
     * @return bool True if the current iterator position is valid, false otherwise. When true, a
     *              call to current() will succeed.
     */
    public function valid()
    {
        return !empty($this->cur_entry);
    }

    /**
     * Set the permissions on the current directory
     */
    public function setPermissions()
    {
        return Path::setPermissions($this->path);
    }
}
