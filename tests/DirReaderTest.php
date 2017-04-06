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

use PHPUnit\Framework\TestCase;

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamWrapper;
use org\bovigo\vfs\vfsStreamDirectory;

/**
 * @covers Wedeto\IO\DirReader
 */
final class DirReaderTest extends TestCase
{
    private $path;

    public function setUp()
    {
        vfsStreamWrapper::register();
        vfsStreamWrapper::setRoot(new vfsStreamDirectory('testdir'));
        $this->path = vfsStream::url('testdir');
    }

    /**
     * @covers Wedeto\IO\DirReader::__construct
     * @covers Wedeto\IO\DirReader::current
     * @covers Wedeto\IO\DirReader::key
     * @covers Wedeto\IO\DirReader::hasNext
     * @covers Wedeto\IO\DirReader::next
     * @covers Wedeto\IO\DirReader::rewind
     */
    public function testDirRead()
    {
        $files = glob("/usr/lib/*");

        // Split into files and dirs
        $file_list = array();
        $dir_list = array();
        foreach ($files as $f)
        {
            $bf = basename($f);
            if ($bf === "." || $bf === "..")
                continue;

            if (is_file($f))
                $file_list[] = $bf;
            elseif (is_dir($f))
                $dir_list[] = $bf;
        }
        sort($file_list);
        sort($dir_list);

        $dir = new DirReader("/usr/lib", DirReader::READ_FILE);
        $files = array();
        $iter = 0;
        foreach ($dir as $k => $f)
        {
            $this->assertEquals($iter++, $k);
            $files[] = $f;
        }

        sort($files);
        $this->assertEquals($files, $file_list);

        $dir = new DirReader("/usr/lib", DirReader::READ_DIR);
        $files = array();
        $iter = 0;
        foreach ($dir as $k => $d)
        {
            $this->assertEquals($k, $iter++);
            $dirs[] = $d;
        }
        sort($dirs);
        $this->assertEquals($dirs, $dir_list);

        $this->assertNull($dir->next());
        $this->assertFalse($dir->hasNext());
    }
}

