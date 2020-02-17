<?php
declare(strict_types=1);

namespace Azonmedia\Filesystem;


use Azonmedia\Utilities\GeneralUtil;
use Azonmedia\Exceptions\PermissionDeniedException;
use Azonmedia\Exceptions\InvalidArgumentException;
use Azonmedia\Exceptions\RunTimeException;
use Azonmedia\Exceptions\RecordNotFoundException;
use Azonmedia\Translator\Translator as t;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Class File
 * @package GuzabaPlatform\Assets\Models
 *
 * This class represents a File under the store_
 */
class File
{

    private string $absolute_path;

    private string $relative_path;

    private static ?string $absolute_store_path = NULL;

    /**
     * @param string $relative_path
     */
    public function __construct(string $relative_path)
    {
        $real_store_base_path = static::get_absolute_store_path();
        $relative_path = static::validate_relative_path($relative_path);
        $absolute_path = $real_store_base_path.'/'.$relative_path;
        $real_absolute_path = realpath($absolute_path);
        if (!$real_absolute_path || !file_exists($real_absolute_path)) {
            throw new RecordNotFoundException(sprintf(t::_('The file/dir %s does not exist.'), $relative_path));
        }
        if (!is_readable($real_absolute_path)) {
            throw new PermissionDeniedException(sprintf(t::_('The file/dir %s is not readable. Please check the filesystem permissions'), $relative_path), 0, NULL, 'ad0a5c3a-e064-4be1-a523-4fe166b0a40c');
        }
        $this->relative_path = $relative_path;
        $this->absolute_path = $real_absolute_path;
    }

    public function __toString() : string
    {
        return $this->get_name();
    }

    /**
     * Does not check permissions
     */
    public function delete() : void
    {
        unlink($this->absolute_path);
        $this->absolute_path = '';
        $this->relative_path = '';
    }

    public function move(string $to_relative_path) : void
    {
        $real_store_base_path = static::get_absolute_store_path();
        $to_relative_path = static::validate_relative_path($to_relative_path);
        $absolute_to_path = $real_store_base_path.$to_relative_path;
        $real_absolute_to_path = realpath($absolute_to_path);
        if (!rename($this->absolute_path, $real_absolute_to_path)) {
            throw new RunTimeException(sprintf(t::_('Moving file %s to %s failed.'), $this->relative_path, $to_relative_path));
        }
        $this->absolute_path = $real_absolute_to_path;
        $this->relative_path = $to_relative_path;
    }

    public function copy(string $new_relative_path) : void
    {

    }

    public function get_relative_path() : string
    {
        return $this->relative_path;
    }

    public function get_absolute_path() : string
    {
        return $this->absolute_path;
    }


    public function get_name() : string
    {
        return basename($this->relative_path);
    }

    public function get_dir() : string
    {
        return dirname($this->relative_path);
    }

    public function is_dir() : bool
    {
        return is_dir($this->absolute_path);
    }

    public function is_file() : bool
    {
        return is_file($this->absolute_path);
    }

    public function get_extension() : string
    {
        if ($this->is_dir()) {
            throw new RunTimeException(sprintf(t::_('Can not obtain extension on directory %s.'), $this->relative_path));
        }
    }

    public function get_mime_type() : string
    {
        if ($this->is_dir()) {
            throw new RunTimeException(sprintf(t::_('Can not obtain mime type on directory %s.'), $this->relative_path));
        }
        return mime_content_type($this->absolute_path);
    }

    /**
     * Applicable only on directories (@see self::is_dir())
     * Returns an array of File
     * @return array
     */
    public function get_files() : array
    {
        $ret = [];
        $files = scandir($this->absolute_path);
        foreach ($files as $path) {
            if ($path === '.' || $path === '..') {
                continue;
            }
            if (is_link($this->absolute_path.'/'.$path)) {
                continue;
            }
            $ret[] = new static($this->relative_path.'/'.$path);
        }
        return $ret;
        //return array_map( fn(string $path) : self => new static($path) , scandir($this->absolute_path) );
    }

    /**
     * Returns the parent dir (File object), NULL if this is the root of the store
     * @return $this|null
     */
    public function get_parent() : ?self
    {

    }

    /**
     * Applicable only on files (@see self::is_file())
     * @return string
     */
    public function get_contents() : string
    {
        return file_get_contents($this->absolute_path);
    }

    //==================== Factory methods ====================

    /**
     * @param string $relative_path
     * @return static
     * @throws PermissionDeniedException
     * @throws RecordNotFoundException
     * @throws RunTimeException
     */
    public static function create_dir(string $relative_path) : self
    {
        self::create_process($relative_path, function() use ($relative_path, $content) {
            if (mkdir($real_absolute_path) === FALSE) {
                throw new RunTimeException(sprintf(t::_('The creation of directory %s failed.'), $relative_path));
            }
        });
        return new static($relative_path);
    }

    public static function create_file(string $relative_path, string $content) : self
    {
        self::create_process($relative_path, function() use ($relative_path, $content) {
            if (file_put_contents($real_absolute_path, $content) === FALSE) {
                throw new RunTimeException(sprintf(t::_('The creation of file %s failed.'), $relative_path));
            }
        });
        return new static($relative_path);
    }

    /**
     * @param string $relative_path The path where the file should be placed
     * @param UploadedFileInterface $UploadedFile
     * @return static
     */
    public static function upload_file(string $relative_path, UploadedFileInterface $UploadedFile) : self
    {

        $target_path = static::get_absolute_store_path().'/'.$relative_path.'/'.$UploadedFile->getClientFilename();
        $target_dir = dirname($target_path);
        $relative_target_path = str_replace(static::get_absolute_store_path().'/', '', $target_path);
        $relative_target_dir = str_replace(static::get_absolute_store_path().'/', '', $target_dir);
        if (!file_exists($target_dir)) {
            throw new InvalidArgumentException(sprintf(t::_('The target directory %1s does not exist.'), $relative_target_dir));
        }
        if (!is_dir($target_dir)) {
            throw new InvalidArgumentException(sprintf(t::_('The target directory %1s is a file.'), $relative_target_dir));
        }
        if (!is_writeable($target_dir)) {
            throw new InvalidArgumentException(sprintf(t::_('The target directory %1s is not writeable.'), $relative_target_dir));
        }
        if (file_exists($target_path)) {
            throw new InvalidArgumentException(sprintf(t::_('The file %1s already exists.'), $relative_target_path));
        }
        $UploadedFile->moveTo($target_path);
        return new static($relative_target_path);
    }

    private static function create_process(string $relative_path, callable $Callback) : void
    {
        $real_store_base_path = self::get_absolute_store_path();
        $relative_path = self::validate_relative_path($relative_path);
        $absolute_path = $real_store_base_path.'/'.$relative_path;
        $real_absolute_path = realpath($absolute_path);
        if (file_exists($real_absolute_path)) {
            if (is_dir($real_absolute_path)) {
                throw new RunTimeException(sprintf(t::_('There is already a directory %s.'), $relative_path));
            } else {
                throw new RunTimeException(sprintf(t::_('There is already a file %s.'), $relative_path));
            }
        }
        $Callback();
    }

    //==================== Static methods ====================

    /**
     * Returns a file based on the provided path.
     * @param string $base_path The base path of the store
     * @param string $absolute_path
     * @return static
     */
    public static function get_by_absolute_path(string $absolute_path) : self
    {

        $real_store_base_path = static::get_absolute_store_path();
        //lets find out is the requested file from private assets (./app/assets) or public assets (./app/public/assets)
        $relative_path = str_replace($real_store_base_path, '', $absolute_path);
        return new static($relative_path);
    }

    /**
     * @param string $relative_path
     * @return string
     * @throws InvalidArgumentException
     * @throws RunTimeException
     */
    public static function validate_relative_path(string $relative_path) : string
    {
        $real_store_base_path = static::get_absolute_store_path();
        if (!$relative_path) {
            throw new InvalidArgumentException(sprintf(t::_('There is no path provided.')));
        }
        if ($relative_path[0] === '/') {
            throw new InvalidArgumentException(sprintf(t::_('The provided path %s is absolute. Relative path (to store base %s) is expected.'), $relative_path, $real_store_base_path ));
        }
//        if ($relative_path === './') {
//            throw new InvalidArgumentException(sprintf('The provided path %s is invalid.', $relative_path));
//        }
        if ($relative_path[0] !== '.') {
            $relative_path = './'.$relative_path;
        }
        return $relative_path;
    }

    /**
     * The provided path must be absolute.
     * If it has a trailing / it will be removed.
     * @param string $absolute_path
     * @throws InvalidArgumentException
     */
    public static function set_absolute_store_path(string $absolute_path) : void
    {
        if ($absolute_path[0] !== '/') {
            throw new InvalidArgumentException(sprintf(t::_('The provided path %s is not absolute.'), $absolute_path));
        }
        if ($absolute_path[-1] === '/') {
            $absolute_path = substr($absolute_path, 0, strlen($absolute_path) - 1);
        }
        self::$absolute_store_path = $absolute_path;
    }

    /**
     * The returned path does not have a trailing /
     * @return string
     * @throws InvalidArgumentException
     * @throws RunTimeException
     */
    public static function get_absolute_store_path() : string
    {
        if (self::$absolute_store_path === NULL) {
            throw new RunTimeException(sprintf(t::_('The absolute store path is not set. Please set it with File::set_absolute_store_path().')));
        }
        return self::$absolute_store_path;
    }



}