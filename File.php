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

use WASP\PermissionError;
use WASP\Debug\LoggerAwareStaticTrait;
use Throwable;

class File
{
    use LoggerAwareStaticTrait;

    private $dir;
    private $path;
    private $filename;
    private $basename;
    private $ext;
    private $mime;

    private static $file_group = null;
    private static $file_mode = null;
    private static $dir_mode = null;

    const OWNER_READ = 0400;
    const OWNER_WRITE = 0200;
    const OWNER_EXECUTE = 0100;

    const GROUP_READ = 0040;
    const GROUP_WRITE = 0020;
    const GROUP_EXECUTE = 0010;

    const WORLD_READ = 0040;
    const WORLD_WRITE = 0020;
    const WORLD_EXECUTE = 0010;

    public function __construct(string $filename, $mime = null)
    {
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

    public static function setFileGroup(string $group)
    {
        self::$file_group = $group;
    }

    public static function setFileMode(int $mode)
    {
        self::$file_mode = $mode;
    }

    public static function setDirMode(int $mode)
    {
        self::$dir_mode = $mode;
    }
    
    public static function getPermissions($path)
    {
        try
        {
            $mode = @fileperms($path);
        }
        catch (Throwable $e)
        {
            throw new IOException("Could not stat: " . $path);
        }

        $perms = array(
            'owner' => array(
                'read'    => (bool)($mode & 0x0100),
                'write'   => (bool)($mode & 0x0080),
                'execute' => (bool)($mode & 0x0040)
            ),
            'group' => array(
                'read'    => (bool)($mode & 0x0020),
                'write'   => (bool)($mode & 0x0010),
                'execute' => (bool)($mode & 0x0008)
            ),
            'world' => array(
                'read'    => (bool)($mode & 0x0004),
                'write'   => (bool)($mode & 0x0002),
                'execute' => (bool)($mode & 0x0001)
            )
        );

        $fmode = 0;
        $fmode |= $perms['owner']['read']    ? self::OWNER_READ    : 0;
        $fmode |= $perms['owner']['write']   ? self::OWNER_WRITE   : 0;
        $fmode |= $perms['owner']['execute'] ? self::OWNER_EXECUTE : 0;

        $fmode |= $perms['group']['read']    ? self::GROUP_READ    : 0;
        $fmode |= $perms['group']['write']   ? self::GROUP_WRITE   : 0;
        $fmode |= $perms['group']['execute'] ? self::GROUP_EXECUTE : 0;

        $fmode |= $perms['world']['read']    ? self::WORLD_READ    : 0;
        $fmode |= $perms['world']['write']   ? self::WORLD_WRITE   : 0;
        $fmode |= $perms['world']['execute'] ? self::WORLD_EXECUTE : 0;

        $perms['mode'] = $fmode;
        return $perms;
    }

    public static function makeWritable($path)
    {
        $perms = self::getPermissions($path);

        $current_user = posix_getpwuid(posix_geteuid());
        $owner = posix_getpwuid(fileowner($path));
        $group = posix_getgrgid(filegroup($path));

        $is_owner = $current_user['uid'] === $owner['uid'];
        if ($is_owner && $perms['owner']['write'])
            return;

        if (!$is_owner)
        {
            // Check if the file is owner by a group we're in
            $my_groups = posix_getgroups();
            if (in_array($group['gid'], $my_groups) && $perms['group']['write'])
                return;

            // Not in the same group, check if the file is world writable
            if ($perms['world']['write'])
                return;

            // The file is really unwritable, and we cannot change the permissions
            throw new PermissionError($path, "Cannot change permissions - not the owner");
        }

        // We own the file, so we should be able to fix it
        $set_gid = false;
        if (self::$file_group !== null)
        {
            if (self::$file_group !== $group['name'] && !chgrp($path, self::$file_group))
                throw new PermissionError($path, "Cannot change group");
            $set_gid = true;
        }

        // Owner and group are all right now, we should be able to modify the permissions
        $new_mode = $perms['mode'] | self::OWNER_WRITE | ($set_gid ? self::GROUP_WRITE : 0);

        if (is_dir($path))
            $new_mode |= self::OWNER_EXECUTE | ($set_gid ? self::GROUP_EXECUTE : 0);

        if ($new_mode === $perms['mode'])
            return;

        $what = is_dir($path) ? "directory" : "file";
        self::$logger->notice(
            "Changing permissions of {0} {1} to {2} (was: {3})", 
            [$what, $path, $new_mode, $perms['mode']]
        );

        try
        {
            @chmod($path, $new_mode);
        }
        catch (Throwable $e)
        {
            throw new PermissionError($path, "Could not set permissions");
        }
    }

    public function touch()
    {
        // Check permissions
        if (file_exists($this->path))
        {
            if (!is_writable($this->path))
                self::makeWritable($this->path);
        }

        touch($this->path);
        $this->setPermissions();
    }

    public function setPermissions()
    {
        $is_dir = is_dir($this->path);

        $current_uid = posix_getuid();
        $owner = @fileowner($this->path);
        if ($current_uid !== $owner)
            return;

        if (self::$file_group)
        {
            $current_gid = filegroup($this->path);
            $grpinfo = posix_getgrnam(self::$file_group);
            $wanted_gid = $grpinfo['gid'];
            if ($wanted_gid !== $current_gid)
            {
                try
                {
                    @chgrp($this->path, $wanted_gid);
                }
                catch (Throwable $e)
                {
                    throw new IOException(
                        "Could not set group on " . $this->path . " to " . self::$file_group
                    );
                }
            }
        }
        
        $wanted_mode = $is_dir ? self::$dir_mode : self::$file_mode;
        if (!empty($wanted_mode))
        {
            $perms = self::getPermissions($this->path);
            $current_mode = $perms['mode'];
            
            if ($wanted_mode !== $current_mode)
            {
                try
                {
                    @chmod($this->path, $wanted_mode);
                }
                catch (Throwable $e)
                {
                    throw new IOException(
                        "Could not set mode on " . $this->path . " to " . $wanted_mode
                    );
                }
            }
        }
    }

    public function getExt()
    {
        return $this->ext;
    }

    public function setExt($ext)
    {
        if ($this->dir)
            return $this->dir . "/" . $this->basename . "." . $ext;
        return $this->basename . "." . $ext;
    }

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

    public function getPath()
    {
        return $this->path;
    }

    public function addSuffix($suffix)
    {
        if ($this->dir)
            return $this->dir . "/" . $this->basename . $suffix . "." . $this->ext;
        return $this->basename . $suffix . "." . $this->ext;
    }

    public function getFilename()
    {
        return $this->filename; 
    }

    public function getDir()
    {
        return $this->dir;
    }
    
    public function getBaseName()
    {
        return $this->basename;
    }
}

// @codeCoverageIgnoreStart
File::setLogger();
// @codeCoverageIgnoreEnd
