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

use Wedeto\Util\Hook;

/**
 * @covers Wedeto\IO\Path
 */
final class PathTest extends TestCase
{
    private $path;
    private $real_dir;

    public function setUp()
    {
        vfsStreamWrapper::register();
        vfsStreamWrapper::setRoot(new vfsStreamDirectory('testdir'));
        $this->path = vfsStream::url('testdir');

        Path::setRequiredPrefix($this->path);

        $this->real_dir = __DIR__ . '/var';
    }

    public function tearDown()
    {
        $rd = __DIR__ . '/var/testpermissions';
        if (file_exists($rd) && is_dir($rd))
        {
            Path::setRequiredPrefix($rd);
            Path::makeWritable($rd);
            Path::rmtree($rd);
        }

        Path::setDefaultFileGroup();
        Path::setDefaultFileMode(0660);
        Path::setDefaultDirMode(0770);
    }

    /**
     * @covers Wedeto\IO\Path::setRequiredPrefix
     * @covers Wedeto\IO\Path::mkdir
     * @covers Wedeto\IO\Path::rmtree
     */
    public function testPath()
    {
        $dir0 = $this->path . '/var';
        $dir1 = $dir0 . '/testdir';

        $dir2 = $dir1 . '/test2';
        Path::setRequiredPrefix($dir2);
        Path::mkdir($dir2);

        $this->assertTrue(file_exists($dir1), "$dir1 should exist");
        $this->assertTrue(file_exists($dir2), "$dir2 should exist");
        $this->assertTrue(is_dir($dir1));
        $this->assertTrue(is_dir($dir2));

        $file = $dir2 . '/test.file';
        $fh = fopen($file, 'w');
        fputs($fh, 'test');
        fclose($fh);

        $this->assertTrue(file_exists($file));

        Path::rmtree($dir2);
        $this->assertFalse(file_exists($file));
        $this->assertFalse(file_exists($dir2));
        $this->assertTrue(file_exists($dir1));
        $this->assertTrue(is_dir($dir1));

        $success = true;
        try
        {
            Path::rmtree($dir1);
        }
        catch (\Throwable $e)
        {
            $this->assertInstanceOf(\RuntimeException::class, $e);
            $success = false;
        }

        $this->assertFalse($success);

        $this->assertTrue(file_exists($dir1));
        $this->assertTrue(is_dir($dir1));

        Path::setRequiredPrefix($dir0);
        Path::rmtree($dir1);
        $this->assertFalse(file_exists($dir1));
        $this->assertFalse(is_dir($dir1));

        Path::rmtree($dir1);
    }

    /**
     * @covers Wedeto\IO\Path::mkdir
     * @covers Wedeto\IO\Path::rmtree
     */
    public function testRMDirPermission()
    {
        $dir0 = $this->path . '/var';
        $dir1 = $dir0 . '/testdir';

        Path::mkdir($dir1);
        chmod($dir1, 000);

        Path::rmtree($dir1);
        $this->assertFalse(file_exists($dir1));
    }

    /**
     * @covers Wedeto\IO\Path::mkdir
     * @covers Wedeto\IO\Path::rmtree
     */
    public function testRMFile()
    {
        $dir0 = $this->path . '/var';
        $dir1 = $dir0 . '/testdir';
        Path::mkdir($dir1);

        $file = $dir1 . '/test.file';
        $fh = fopen($file, 'w');
        fputs($fh, 'test');
        fclose($fh);

        $this->assertTrue(file_exists($file));
        Path::rmtree($file);
        $this->assertFalse(file_exists($file));
        $this->assertTrue(file_exists($dir1));
        $this->assertTrue(is_dir($dir1));
    }

    /**
     * @covers Wedeto\IO\Path::mkdir
     * @covers Wedeto\IO\Path::rmtree
     */
    public function testRMFilePermission()
    {
        $dir0 = $this->path . '/var';
        $dir1 = $dir0 . '/testdir';
        Path::mkdir($dir1);

        $file = $dir1 . '/test.file';
        $fh = fopen($file, 'w');
        fputs($fh, 'test');
        fclose($fh);
        chmod($file, 0000);

        Path::rmtree($dir1);
        $this->assertFalse(file_exists($dir1));
    }

    /**
     * @covers Wedeto\IO\Path::mkdir
     * @covers Wedeto\IO\Path::rmtree
     */
    public function testRMDirDeepPermission()
    {
        $dir0 = $this->path . '/var';
        $dir1 = $dir0 . '/testdir';
        $dir2 = $dir1 . '/test2';
        Path::mkdir($dir2);

        chmod($dir2, 0000);

        Path::rmtree($dir1);
        $this->assertFalse(file_exists($dir1));
    }

    public function testHook()
    {
        // Permissions need real IO - VFS doesn't support it unfortunately
        $path = 'file:///' . $this->real_dir;
        Path::setRequiredPrefix($path);

        $dir0 = $path . '/testpermissions';
        if (file_exists($dir0))
            Path::rmtree($dir0);

        $my_groups = posix_getgroups();
        $groups = [];
        foreach ($my_groups as $gid)
        {
            $info = posix_getgrgid($gid);
            $groups[$gid] = $info['name'];
        }
        
        mkdir($dir0, 0700);
        foreach ($groups as $gr)
        {
            Path::setDefaultFileGroup($gr);
            $this->assertEquals($gr, Path::getDefaultFileGroup());
            Hook::execute("Wedeto.IO.DirCreated", ['path' => $dir0]);

            $st = stat($dir0);
            $gid = $st['gid'];
            $gn = $groups[$gid] ?? null;

            $this->assertEquals($gr, $gn);
        }

        foreach ([0777, 0770, 0500, 0700] as $mode)
        {
            Path::setDefaultDirMode($mode);
            $this->assertEquals($mode, Path::getDefaultDirMode());
            Hook::execute("Wedeto.IO.DirCreated", ['path' => $dir0]);

            $perms = Path::getPermissions($dir0);
            $this->assertEquals($mode, $perms['mode']);
        }

        $file = $dir0 . '/testfile';
        touch($file);
        foreach ($groups as $gr)
        {
            Path::setDefaultFileGroup($gr);
            $this->assertEquals($gr, Path::getDefaultFileGroup());
            Hook::execute("Wedeto.IO.FileCreated", ['path' => $file]);

            $st = stat($file);
            $gid = $st['gid'];
            $gn = $groups[$gid] ?? null;

            $this->assertEquals($gr, $gn);
        }

        foreach ([0777, 0770, 0700, 0500] as $mode)
        {
            Path::setDefaultFileMode($mode);
            $this->assertEquals($mode, Path::getDefaultFileMode());
            Hook::execute("Wedeto.IO.FileCreated", ['path' => $file]);

            $perms = Path::getPermissions($file);
            $this->assertEquals($mode, $perms['mode']);
        }

        chmod($dir0, 0400);
        clearstatcache(true, $dir0);
        Path::makeWritable($dir0);

        clearstatcache(true, $dir0);
        $this->assertTrue(is_writable($dir0));

        // We need a non-existing filename
        $file = tempnam(sys_get_temp_dir(), 'pathtest');
        unlink($file);

        Path::setPermissions($file);
        $this->expectException(IOException::class);
        $this->expectExceptionMessage("Could not stat");
        $perms = Path::getPermissions($file);
    }

    public function testInvalidDir()
    {
        $path = $this->real_dir;
        $dir0 = $path . '/testpermissions';
        mkdir($dir0);

        Path::setRequiredPrefix("");

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Safety measure: required prefix");
        Path::rmtree($dir0);
    }
}

