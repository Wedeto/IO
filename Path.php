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

use Throwable;

use WASP\Util\LoggerAwareStaticTrait;
use WASP\Util\Hook;

/**
 * Provide some tools for creating and removing directories.
 */
class Path
{
    use LoggerAwareStaticTrait;

    const OWNER_READ = 0400;
    const OWNER_WRITE = 0200;
    const OWNER_EXECUTE = 0100;

    const GROUP_READ = 0040;
    const GROUP_WRITE = 0020;
    const GROUP_EXECUTE = 0010;

    const WORLD_READ = 0040;
    const WORLD_WRITE = 0020;
    const WORLD_EXECUTE = 0010;

    /** 
     * The required prefix for removing directory trees. Must be set prior to
     * calling rmtree, as a security measure.
     */
    private static $required_prefix = null;

    /** The group ownership of created files and directories */
    private static $file_group = null;

    /** The file mode of created files */
    private static $file_mode = 0660;

    /** The file mode of created directories */
    private static $dir_mode = 0770;

    /** 
     * Security measure that prevents attempts of removing files outside of WASP
     * Each rmtree'd path should have this prefix, otherwise the command is not executed.
     * @param $prefix string The prefix that should be required on each path for rmtree
     */
    public static function setRequiredPrefix(string $prefix)
    {
        self::$required_prefix = $prefix;
    }

    /**
     * Set the default file group for files, when setPermissions is called
     * @param string $group The group name
     */
    public static function setDefaultFileGroup(string $group)
    {
        self::$file_group = $group;
    }

    /**
     * Set the default file mode for files, when setPermission is called or
     * when a new file is created
     * @param int $mode The octal file mode
     */
    public static function setDefaultFileMode(int $mode)
    {
        self::$file_mode = $mode;
    }

    /**
     * Set the default file mode for directories, when setPermissions is called
     * or when a new directory is created.
     * @param int $mode The octal file mode
     */
    public static function setDefaultDirMode(int $mode)
    {
        self::$dir_mode = $mode;
    }
    

    /**
     * Make a directory and its parents. When all directories already exist, nothing happens.
     * Newly created directories are chmod'ded to 0770: RWX for owner and group.
     *
     * @param $path string The path to create
     */
    public static function mkdir(string $path)
    {
        $parts = explode("/", $path);

        $path = "";
        foreach ($parts as $p)
        {
            $path .= $p . '/';
            if (!is_dir($path))
            {
                mkdir($path);
                self::setPermissions($path);
            }
        }
    }

    /**
     * Delete a directory and its contents. The provided path must be inside the configured prefix.
     * @param $path string The path to remove
     * @return int Amount of files and directories that have been deleted
     */
    public static function rmtree(string $path)
    {
        $path = realpath($path);
        if (empty($path)) // File/dir does not exist
            return true;

        if (self::$required_prefix === null)
            throw new \RuntimeException("Safety measure: required prefix needs to be set before running rmtree");

        if (strpos($path, self::$required_prefix) !== 0)
            throw new \RuntimeException("Refusing to remove directory outside " . self::$required_prefix);

        self::checkWrite($path);

        if (!is_dir($path))
            return unlink($path) ? 1 : 0;

        $cnt = 0;
        $d = \dir($path);
        while (($entry = $d->read()) !== false)
        {
            if ($entry === "." || $entry === "..")
                continue;

            $entry = $path . '/' . $entry;
            self::checkWrite($entry);

            if (is_dir($entry))
                $cnt += self::rmtree($entry);
            else
                $cnt += (unlink($entry) ? 1 : 0);
        }

        rmdir($path);
        return $cnt + 1;
    }

    /**
     * @codeCoverageIgnore This cannot be unit tested - to create a file that cannot
     * be chmod'ed, it needs to be owned by someone else.
     */
    private static function checkWrite(string $path)
    {
        if (!is_writable($path) && @chmod($path, 0666) === false)
            throw new \RuntimeException("Cannot delete $path - permission denied");
    }

    /** 
     * Attempt to gain write access to the file. This will only work on files
     * that are owned but not writable - e.g. rarely.
     */
    public static function makeWritable(string $path)
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
        self::getLogger()->notice(
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

    /**
     * Return the permissions set on the specified file.
     * @param string $path The path to examine
     * @return array An associative array containing 'owner', 'group' and 'world',
     *               members, each containing 'read', 'write' and 'execute'
     *               indicating their permissions.
     */
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

        $perms['mode'] = self::compileMode($perms);
        return $perms;
    }

    /** 
     * Create a file mode from an array containing permissions. The array
     * can have 'owner', 'group' and 'world' members, each which can have
     * 'read', 'write', and 'execute' booleans that indicate whether that
     * permission is present or not.
     */
    public static function compileMode(array $perms)
    {
        $fmode = 0;
        $fmode |= !empty($perms['owner']['read'])    ? self::OWNER_READ    : 0;
        $fmode |= !empty($perms['owner']['write'])   ? self::OWNER_WRITE   : 0;
        $fmode |= !empty($perms['owner']['execute']) ? self::OWNER_EXECUTE : 0;

        $fmode |= !empty($perms['group']['read'])    ? self::GROUP_READ    : 0;
        $fmode |= !empty($perms['group']['write'])   ? self::GROUP_WRITE   : 0;
        $fmode |= !empty($perms['group']['execute']) ? self::GROUP_EXECUTE : 0;

        $fmode |= !empty($perms['world']['read'])    ? self::WORLD_READ    : 0;
        $fmode |= !empty($perms['world']['write'])   ? self::WORLD_WRITE   : 0;
        $fmode |= !empty($perms['world']['execute']) ? self::WORLD_EXECUTE : 0;

        return $fmode;
    }

    /** 
     * The hook connecting to newly created files
     */
    public static function hookFileCreated(array $params)
    {
        $f = new File($params['filename']);
        $f->setPermissions();
    }

    /** 
     * Set / fix the permissions as specified in the configuration
     * @param string $path The path to update
     * @param int $mode The (octal) mode to set. When omitted, the default is
     *                  used.
     */
    public function setPermissions(string $path, int $mode = null)
    {
        $is_dir = is_dir($path);

        $current_uid = posix_getuid();
        $owner = @fileowner($path);
        if ($current_uid !== $owner)
            return;

        if (self::$file_group)
        {
            $current_gid = filegroup($path);
            $grpinfo = posix_getgrnam(self::$file_group);
            $wanted_gid = $grpinfo['gid'];
            if ($wanted_gid !== $current_gid)
            {
                try
                {
                    @chgrp($path, $wanted_gid);
                }
                catch (Throwable $e)
                {
                    throw new IOException(
                        "Could not set group on " . $path . " to " . self::$file_group
                    );
                }
            }
        }
        
        if ($mode === null)
            $mode = $is_dir ? self::$dir_mode : self::$file_mode;

        if (!empty($mode))
        {
            $perms = self::getPermissions($path);
            $current_mode = $perms['mode'];
            
            if ($mode !== $current_mode)
            {
                try
                {
                    @chmod($path, $mode);
                }
                catch (Throwable $e)
                {
                    throw new IOException(
                        "Could not set mode on " . $path . " to " . $mode
                    );
                }
            }
        }
    }
}

Hook::subscribe("WASP.IO.FileCreated", array(Path::class, "hookFileCreated"));
Hook::subscribe("WASP.IO.DirCreated", array(Path::class, "hookFileCreated"));
