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
 * @covers Wedeto\IO\MimeTypes
 */
final class MimeTypesTest extends TestCase
{
    public function testExtractFromPath()
    {
        $paths = [
            "/my/home/test.txt" => ['text/plain', '.txt'],
            '/my/path/test.csv' => ['text/csv', '.csv'],
            '/my/file/video.ogv' => ['video/ogg', '.ogv'],
            '/test/file.exe' => ['application/octet-stream', '.exe'],
            '/test/file.dat' => [null, '.dat'],
            '/test/file' => [null, null]
        ];

        foreach ($paths as $path => $resp)
        {
            $this->assertEquals($resp, MimeTypes::extractFromPath($path));
        }
    }

    public function testFromFile()
    {
        vfsStreamWrapper::register();
        vfsStreamWrapper::setRoot(new vfsStreamDirectory('testdir'));
        $path = vfsStream::url('testdir');

        $paths = [
            $path . "/my/home/test.txt" => 'text/plain',
            $path . '/my/path/test.csv' => 'text/csv',
            $path . '/my/file/video.ogv' => 'video/ogg',
            $path . '/test/file.exe' => 'application/octet-stream',
            $path . '/test/file' => 'video/x-ms-asf'
        ];

        // The last file has no extension, so give it a PDF header to let the
        // PHP mime recognizer recognize it.
        mkdir($path . '/test');

        // WMV header
        $wmv_header =  chr(0x30) . chR(0x26) . chr(0xb2) . chR(0x75) . chr(0x8e)
            . chr(0x66) . chr(0xcf) . chr(0x11) . chr(0xa6) . chr(0xd9) . chr(0x00)
            . chr(0xaa) . chr(0x00) . chr(0x62) . chr(0xce) . chr(0x6c);

        file_put_contents($path . '/test/file', $wmv_header);

        foreach ($paths as $path => $resp)
        {
            $this->assertEquals($resp, MimeTypes::getFromFile($path));
        }
    }

    public function testGetExtension()
    {
        $exts = [
            'text/html' => 'htm',
            'application/javascript' => 'js',
            'text/css' => 'css',
            'application/pdf' => 'pdf'
        ];

        foreach ($exts as $mime => $ext)
        {
            $this->assertEquals($ext, MimeTypes::getExtension($mime));
        }
    }

    public function testIsPlainText()
    {
        $mimes = [
            'text/html' => true,
            'text/html; charset=utf8' => true,
            'application/javascript' => true,
            'text/css' => true,
            'application/pdf' => false,
            'video/ogg' => false
        ];

        foreach ($mimes as $mime => $plain)
        {
            $this->assertEquals($plain, MimeTypes::isPlainText($mime));
        }
    }

}
