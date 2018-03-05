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
 * @covers Wedeto\IO\File
 */
final class FileTest extends TestCase
{
    public function testFile()
    {
        $a = new File('/foo/bar/test.txt');
        $this->assertEquals('/foo/bar/test.txt', $a->getFullPath());
        $this->assertEquals('/foo/bar', $a->getDir());
        $this->assertEquals('test', $a->getBasename());
        $this->assertEquals('test.txt', $a->getFilename());
        $this->assertEquals('txt', $a->getExtension());
        $this->assertEquals('/foo/bar/test-2.txt', $a->withSuffix('-2'));
        $this->assertEquals('text/plain', $a->getMime());
        $this->assertEquals('/foo/bar/test.dat', $a->withExtension('dat'));

        $a = new File('footest.txt', 'application/json');
        $this->assertEquals('footest.txt', $a->getFullPath());
        $this->assertEmpty($a->getDir());
        $this->assertEquals('footest', $a->getBasename());
        $this->assertEquals('footest.txt', $a->getFilename());
        $this->assertEquals('txt', $a->getExtension());
        $this->assertEquals('footest-2.txt', $a->withSuffix('-2'));
        $this->assertEquals('application/json', $a->getMime());
        $this->assertEquals('footest.dat', $a->withExtension('dat'));

        $a = new File('footest', 'application/xml');
        $this->assertEquals('footest', $a->getFullPath());
        $this->assertEmpty($a->getDir());
        $this->assertEquals('footest', $a->getBasename());
        $this->assertEquals('footest', $a->getFilename());
        $this->assertEmpty($a->getExtension());
        $this->assertEquals('footest-2', $a->withSuffix('-2'));
        $this->assertEquals('application/xml', $a->getMime());
        $this->assertEquals('footest.dat', $a->withExtension('dat'));
    }

    public function testPermissions()
    {
        $path = __DIR__ . '/var/file.json';
        try
        {
            touch($path);
            $this->assertTrue(is_writable($path));
            chmod($path, 0400);
            $this->assertFalse(is_writable($path));
            $f = new File($path);
            $f->touch();

            $this->assertTrue(is_writable($path));

            chmod($path, 0400);
            $this->assertFalse(is_writable($path));

            $f->setPermissions();
            $this->assertTrue(is_writable($path));
        }
        finally
        {
            unlink($path);
        }
    }
    
    public function testOpen()
    {
        vfsStreamWrapper::register();
        vfsStreamWrapper::setRoot(new vfsStreamDirectory('testdir'));
        $this->path = vfsStream::url('testdir');
        
        $f = new File($this->path . '/test1.dat');
        $f->touch();
        $fh = $f->open('r');
        $this->assertTrue(is_resource($fh));
        fclose($fh);

        $f = new File($this->path . '/test2.dat');
        $fh = $f->open('w');
        $this->assertTrue(is_resource($fh));
        fclose($fh);

        $f = new File($this->path . '/tests3.dat');
        try
        {
            $fh = $f->open('r');
        }
        catch (IOException $e)
        {
            $this->assertContains('File is not readable', $e->getMessage());
            $thrown = true;
        }
        $this->assertTrue($thrown);

        $thrown = false;
        $f = new File($this->path . '/tests4.dat');
        $f->touch();
        chmod($f->getFullPath(), 0400);
        try
        {
            $fh = $f->open('w');
        }
        catch (IOException $e)
        {
            $this->assertContains('File is not writable', $e->getMessage());
            $thrown = true;
        }
        $this->assertTrue($thrown);

        $thrown = false;
        $f = new File($this->path . '/tests5.dat');
        $f->touch();
        try
        {
            $fh = $f->open('x');
        }
        catch (IOException $e)
        {
            $this->assertContains('File already exists', $e->getMessage());
            $thrown = true;
        }
        $this->assertTrue($thrown);

        $thrown = false;
        $f = new File($this->path . '/tests6.dat');
        try
        {
            $fh = $f->open('bl');
        }
        catch (IOException $e)
        {
            $this->assertContains('Invalid mode for opening file: bl', $e->getMessage());
            $thrown = true;
        }
        $this->assertTrue($thrown);
    }
}
