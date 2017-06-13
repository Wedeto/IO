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
 * FileType lists some common file types used on webpages.
 * It can be used to determine the mime type based on file extension.
 *
 * When provided with a file on the file system, it can also return a proper
 * content type - text types are recognized and served as a correct type. Without
 * looking at file extension, these files are usually server incorrectly when
 * using auto detection. For unknown file extensions, PHP's mime_content_type is
 * used.
 */
class FileType
{
    public static $TYPES = array(
        // Common HTTP data types
        "txt"  => "text/plain",
        "ini"  => "text/ini",
        "htm"  => "text/html",
        "html" => "text/html",
        "js"   => "application/javascript",
        "css"  => "text/css",

        // Data formats
        "csv"  => "text/csv",
        "json" => "application/json",
        "xml"  => "application/xml",
        "yaml" => "text/yaml",

        // Image formats
        "png"  => "image/png",
        "jpg"  => "image/jpeg",
        "jpeg" => "image/jpeg",
        "gif"  => "image/gif",
        "svg"  => "image/svg+xml",

        // Audio formats
        "wav"  => "audio/wav",
        "webm" => "audio/webm",
        "ogg"  => "audio/ogg",
        "mp3"  => "audio/mpeg",
        "flac" => "audio/flac",
        "aac"  => "audio/aac",
        "m4a"  => "audio/mp4",
        "wma"  => "audio/x-ms-wma",

        // Video formats
        "mp4"  => "video/mp4",
        "ogv"  => "video/ogg",
        "webv" => "video/webm",
        "avi"  => "video/avi",
        "mkv"  => "video/mkv",
        "mov"  => "video/quicktime",
        "wmv"  => "video/x-ms-wmv",

        // Document formats
        "pdf"  => "application/pdf",
        "doc"  => "application/msword",
        "xls"  => "application/vnd.ms-excel",
        "ppt"  => "application/vnd.ms-powerpoint",
        "docx" => "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
        "xlsx" => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
        "pptx" => "application/vnd.openxmlformats-officedocument.presentationml.presentation", 
        "odt"  => "application/vnd.oasis.opendocument.text",
        "ods"  => "application/vnd.oasis.opendocument.spreadsheet",
        "odp"  => "application/vnd.oasis.opendocument.presentation",

        // Archive formats
        "gz"   => "application/gzip",
        "xz"   => "application/x-xz",
        "tar"  => "application/x-tar",
        "bz2"  => "application/x-bzip2",
        "tgz"  => "application/gzip",
        "tbz2" => "application/x-bzip2",
        "zip"  => "application/zip",
        "rar"  => "application/x-rar-compressed",

        // Binary formats
        "bin"       => "application/octet-stream",
        "exe"       => "application/octet-stream",
        "multipart" => "multipart/form-data",
    );

    /**
     * Return the mime type based on a file name
     * @param string $path The path to the file
     * @return FileType The File Type for this file
     */
    public static function getFromFile($path)
    {
        if (!($path instanceof File))
            $path = new File($path);

        $ext = $path->getExt();
        $path = $path->getPath();

        $type = new FileType($ext ?? "", "");
        if (empty($type->getMimeType()) && file_exists($path))
            $type->setMimeType(mime_content_type($path));

        return $type;
    }

    /**
     * Get the File Type based on the extension
     * @param string $ext The extension
     * @return FileType The file type object
     */
    public static function getFromExtension(string $ext)
    {
        return new FileType($ext, "");
    }
    
    /**
     * Get the extension for a mime type
     * @param string $mime The mime type
     * @return string The file extension - null if unknown
     */
    public static function getExtension(string $mime)
    {
        $type = new FileType("", $mime);
        return $type->getExt();
    }


    /** The mime type */
    protected $mime_type = null;

    /** The file extension */
    protected $ext = null;

    /**
     * Create the File Type using a extension and a mime type.
     * @param string $ext The file extension. May be omitted if mime type is known
     * @param string $mime_type The mime type. May be omitted if ext is known
     */
    public function __construct(string $ext, string $mime_type)
    {
        $mime_type = strtolower($mime_type);
        $ext = ltrim(strtolower($ext), '.');

        if (empty($ext))
        {
            if ($key = array_search($mime_type, self::$TYPES))
                $ext = $key;
        }

        if (empty($mime_type))
        {
            if (isset(self::$TYPES[$ext]))
                $mime_type = self::$TYPES[$ext];
        }

        $this->mime_type = empty($mime_type) ? null : $mime_type;
        $this->ext = empty($ext) ? null : '.' . $ext;
    }

    /** 
     * Check if a mime type is plain text
     * @param string $mime The Mime type
     * @return bool True if the mime type is plaintext, false if it isn't
     */
    public function isPlainText()
    {
        $sc_pos = strpos($this->mime_type, ';');
        if ($sc_pos !== false)
            $mime_type = substr($this->mime_type, 0, $sc_pos);
        else
            $mime_type = $this->mime_type;
        
        // Some application/* types are plain text
        switch ($mime_type)
        {
            case "application/javascript":
            case "application/json":
            case "application/xml":
                return true;
        }

        // Assume all text/* files to be plain text
        return substr($mime_type, 0, 5) === "text/";
    }

    /**
     * @return string The mime type for this file type
     */
    public function getMimeType()
    {
        return $this->mime_type;
    }

    /**
     * Set the mime type
     * @param string $mime_type The type to set
     * @throws InvalidArgumentException When mime type is not valid
     * @return FileType Provides fluent interface
     */
    public function setMimeType(string $mime_type)
    {
        if (!preg_match('/^[\w_-]+\/[\w_-]+$/', $mime_type))
            throw new \InvalidArgumentException("Not a valid mime type: $mime_type");
        $this->mime_type = $mime_type;
        return $this;
    }

    /**
     * @return string The file extension
     */
    public function getExt()
    {
        return $this->ext;
    }

    /**
     * Set the extension for the file type
     * @return FileType Provides fluent interface
     */
    public function setExt(string $ext)
    {
        $this->ext = $ext;
        return $this;
    }
}
