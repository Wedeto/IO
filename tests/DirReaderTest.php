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

use WASP\System;
use PHPUnit\Framework\TestCase;

/**
 * @covers WASP\IO\Dir
 */
final class DirTest extends TestCase
{
    private $path;

    public function setUp()
    {
        $this->path = System::path();
        $root = $this->path->root;
        if (empty($root))
            throw new \RuntimeException("Need a proper WASP Root");

        Dir::setRequiredPrefix($root);
        $dir0 = $this->path->var;
        $dir1 = $dir0 . '/testdir';
        if (file_exists($dir1))
        {
            chmod($dir1, 0777);
            Dir::rmtree($dir1);
        }
    }

    public function tearDown()
    {
        $dir0 = $this->path->var;
        $dir1 = $dir0 . '/testdir';
        if (!file_exists($dir1))
            return;

        $file = $dir1 . '/test.file';

        chmod($dir1, 0777);
        if (file_exists($file))
            chmod($file, 0777);

        $dir2 = $dir1 . '/test2';
        if (is_dir($dir2))
            chmod($dir2, 0777);
        Dir::rmtree($dir1);
    }

    /**
     * @covers WASP\IO\Dir::setRequiredPrefix
     * @covers WASP\IO\Dir::mkdir
     * @covers WASP\IO\Dir::rmtree
     */
    public function testDir()
    {
        $dir0 = $this->path->var;
        $dir1 = $dir0 . '/testdir';

        $dir2 = $dir1 . '/test2';
        Dir::setRequiredPrefix($dir2);
        Dir::mkdir($dir2);

        $this->assertTrue(file_exists($dir1));
        $this->assertTrue(file_exists($dir2));
        $this->assertTrue(is_dir($dir1));
        $this->assertTrue(is_dir($dir2));

        $file = $dir2 . '/test.file';
        $fh = fopen($file, 'w');
        fputs($fh, 'test');
        fclose($fh);

        $this->assertTrue(file_exists($file));

        Dir::rmtree($dir2);
        $this->assertFalse(file_exists($file));
        $this->assertFalse(file_exists($dir2));
        $this->assertTrue(file_exists($dir1));
        $this->assertTrue(is_dir($dir1));

        $success = true;
        try
        {
            Dir::rmtree($dir1);
        }
        catch (\Throwable $e)
        {
            $this->assertInstanceOf(\RuntimeException::class, $e);
            $success = false;
        }

        $this->assertFalse($success);

        $this->assertTrue(file_exists($dir1));
        $this->assertTrue(is_dir($dir1));

        Dir::setRequiredPrefix($dir0);
        Dir::rmtree($dir1);
        $this->assertFalse(file_exists($dir1));
        $this->assertFalse(is_dir($dir1));

        Dir::rmtree($dir1);
    }

    /**
     * @covers WASP\IO\Dir::mkdir
     * @covers WASP\IO\Dir::rmtree
     */
    public function testRMDirPermission()
    {
        $dir0 = $this->path->var;
        $dir1 = $dir0 . '/testdir';

        Dir::mkdir($dir1);
        chmod($dir1, 000);

        Dir::rmtree($dir1);
        $this->assertFalse(file_exists($dir1));
    }

    /**
     * @covers WASP\IO\Dir::mkdir
     * @covers WASP\IO\Dir::rmtree
     */
    public function testRMFile()
    {
        $dir0 = $this->path->var;
        $dir1 = $dir0 . '/testdir';
        Dir::mkdir($dir1);

        $file = $dir1 . '/test.file';
        $fh = fopen($file, 'w');
        fputs($fh, 'test');
        fclose($fh);

        $this->assertTrue(file_exists($file));
        Dir::rmtree($file);
        $this->assertFalse(file_exists($file));
        $this->assertTrue(file_exists($dir1));
        $this->assertTrue(is_dir($dir1));
    }

    /**
     * @covers WASP\IO\Dir::mkdir
     * @covers WASP\IO\Dir::rmtree
     */
    public function testRMFilePermission()
    {
        $dir0 = $this->path->var;
        $dir1 = $dir0 . '/testdir';
        Dir::mkdir($dir1);

        $file = $dir1 . '/test.file';
        $fh = fopen($file, 'w');
        fputs($fh, 'test');
        fclose($fh);
        chmod($file, 0000);

        Dir::rmtree($dir1);
        $this->assertFalse(file_exists($dir1));
    }

    /**
     * @covers WASP\IO\Dir::mkdir
     * @covers WASP\IO\Dir::rmtree
     */
    public function testRMDirDeepPermission()
    {
        $dir0 = $this->path->var;
        $dir1 = $dir0 . '/testdir';
        $dir2 = $dir1 . '/test2';
        Dir::mkdir($dir2);

        chmod($dir2, 0000);

        Dir::rmtree($dir1);
        $this->assertFalse(file_exists($dir1));
    }

    /**
     * @covers WASP\IO\Dir::__construct
     * @covers WASP\IO\Dir::current
     * @covers WASP\IO\Dir::key
     * @covers WASP\IO\Dir::hasNext
     * @covers WASP\IO\Dir::next
     * @covers WASP\IO\Dir::rewind
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

        $dir = new Dir("/usr/lib", Dir::READ_FILE);
        $files = array();
        $iter = 0;
        foreach ($dir as $k => $f)
        {
            $this->assertEquals($iter++, $k);
            $files[] = $f;
        }

        sort($files);
        $this->assertEquals($files, $file_list);

        $dir = new Dir("/usr/lib", Dir::READ_DIR);
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

