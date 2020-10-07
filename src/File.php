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

//TODO update to extend SplFileObject

/**
 * Class File
 * @package GuzabaPlatform\Assets\Models
 *
 * @property file_name
 * @property file_relative_name
 * @property file_absolute_name
 * @property file_ctime
 * @property file_atime
 * @property file_mtime
 * @property file_dir
 * @property file_is_dir
 * @property file_is_deleted
 * @property file_type
 * @property file_mime_type
 * @property file_extension
 * @property file_contents
 * @property file_size
 * @property file_permissions
 * @property file_group
 * @property file_owner
 * @property file_inode
 */
class File
{

    protected const PROPERTIES_GET_METHODS_MAP = [
        'file_name'             => 'get_name',
        'file_relative_name'    => 'get_relative_path',
        'file_absolute_name'    => 'get_absolute_path',
        'file_ctime'            => 'get_ctime',
        'file_atime'            => 'get_atime',
        'file_mtime'            => 'get_mtime',
        'file_dir'              => 'get_dir',
        'file_is_dir'           => 'is_dir',
        'file_is_deleted'       => 'is_deleted',
        'file_type'             => 'get_type',
        'file_mime_type'        => 'get_mime_type',
        'file_extension'        => 'get_extension',
        'file_contents'         => 'get_contents',
        'file_size'             => 'get_size',
        'file_permissions'      => 'get_permissions',
        'file_group'            => 'get_group',
        'file_owner'            => 'get_owner',
        'file_inode'            => 'get_inode',
    ];

    /**
     * @var string|null
     */
    private static ?string $absolute_store_path = NULL;

    /**
     * @var string
     */
    private string $absolute_path;

    /**
     * @var string
     */
    private string $relative_path;

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
     * @param string $property
     * @return mixed
     * @throws InvalidArgumentException
     * @throws RunTimeException
     */
    public function __get(string $property) /* mixed */
    {
        if (!isset(self::PROPERTIES_GET_METHODS_MAP[$property])) {
            throw new RunTimeException(sprintf(t::_('The class %1$s does not have a property %2$s.'), get_class($this), $property));
        }
        return $this->{self::PROPERTIES_GET_METHODS_MAP[$property]}();
    }

    public function get_property_names() : array
    {
        return array_keys(self::PROPERTIES_GET_METHODS_MAP);
    }

    /**
     * Does not check permissions
     */
    public function delete() : void
    {
        if ($this->is_file()) {
            unlink($this->absolute_path);
        } elseif ($this->is_dir()) {
            rmdir($this->absolute_path);
        } else {
            throw new RunTimeException(sprintf(t::_('The filesystem object %1$s is neither a file or a directory.'), $this->get_relative_path() ));
        }

        $this->absolute_path = '';
        $this->relative_path = '';
    }

    public function move(string $to_relative_path) : void
    {
        $real_store_base_path = static::get_absolute_store_path();
        $to_relative_path = static::validate_relative_path($to_relative_path);
        $absolute_to_path = $real_store_base_path.'/'.$to_relative_path;
        //$real_absolute_to_path = realpath($absolute_to_path);//the destination is not expected to exist
        $real_absolute_to_path = $absolute_to_path;
        if (!rename($this->absolute_path, $real_absolute_to_path)) {
            throw new RunTimeException(sprintf(t::_('Moving file %s to %s failed.'), $this->relative_path, $to_relative_path));
        }
        $this->absolute_path = $real_absolute_to_path;
        $this->relative_path = $to_relative_path;
    }

    public function copy(string $new_relative_path) : void
    {

    }

    public function is_deleted() : bool
    {
        return strlen($this->relative_path) ? FALSE : TRUE;
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

    /**
     * @return int
     */
    public function get_size() : int
    {
        return filesize($this->absolute_path);
    }

    public function get_ctime() : int
    {
        return filectime($this->absolute_path);
    }

    public function get_mtime() : int
    {
        return filemtime($this->absolute_path);
    }

    public function get_atime() : int
    {
        return fileatime($this->absolute_path);
    }

    public function get_permissions() : int
    {
        return fileperms($this->absolute_path);
    }

    public function get_inode() : int
    {
        return fileinode($this->absolute_path);
    }

    public function get_group() : int
    {
        return filegroup($this->absolute_path);
    }

    public function get_owner() : int
    {
        return fileowner($this->absolute_path);
    }

    public function is_dir() : bool
    {
        return is_dir($this->absolute_path);
    }

    public function is_file() : bool
    {
        return is_file($this->absolute_path);
    }

    public function get_type() : string
    {
        return filetype($this->absolute_path);
    }

    public function get_extension() : string
    {
        if ($this->is_dir()) {
            throw new RunTimeException(sprintf(t::_('Can not obtain extension on directory %1$s.'), $this->relative_path));
        }
        return pathinfo($this->absolute_path, PATHINFO_EXTENSION)['extension'] ?? '';
    }

    public function get_mime_type() : string
    {
//        if ($this->is_dir()) {
//            throw new RunTimeException(sprintf(t::_('Can not obtain mime type on directory %1$s.'), $this->relative_path));
//        }
        return $this->is_file() ? mime_content_type($this->absolute_path) : 'application/x-directory';
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
        //TODO implement...
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
    public static function create_dir(string $relative_path, string $new_directory_name) : self
    {
        $relative_path = self::validate_relative_path($relative_path);
//        if (!$new_directory_name) {
//            throw new InvalidArgumentException(sprintf(t::_('There is no $new_directory_name provided.')));
//        }
//        if (strpos($new_directory_name, '/') !== FALSE) {
//            throw new InvalidArgumentException(sprintf(t::_('The $new_directory_name %1$s contains "/". This is an invalid name.')));
//        }
//        if (strpos($new_directory_name, '..') !== FALSE) {
//            throw new InvalidArgumentException(sprintf(t::_('The $new_directory_name %1$s contains "..". This is an invalid name.')));
//        }
        self::validate_file_name($new_directory_name);
        $dir_absolute_path = self::create_process($relative_path, function(string $real_absolute_path) use ($new_directory_name) : string
        {
            $dir_absolute_path = $real_absolute_path.'/'.$new_directory_name;
            self::check_file_does_not_exist($dir_absolute_path);
            if (mkdir($dir_absolute_path, 0777, true) === FALSE) {
                throw new RunTimeException(sprintf(t::_('The creation of directory %s failed.'), $dir_absolute_path));
            }
            return $dir_absolute_path;
        });
        return static::get_by_absolute_path($dir_absolute_path);
    }

    /**
     * @param string $relative_path
     * @param string $content
     * @return static
     * @throws InvalidArgumentException
     * @throws PermissionDeniedException
     * @throws RecordNotFoundException
     * @throws RunTimeException
     */
    public static function create_file(string $relative_path, string $new_file_name, string $content): self
    {
        $relative_path = self::validate_relative_path($relative_path);
        self::validate_file_name($new_file_name);
        $file_absolute_file_path = self::create_process($relative_path, function(string $real_absolute_path) use ($new_file_name, $content) : string
        {
            $file_absolute_path = $real_absolute_path.'/'.$new_file_name;
            self::check_file_does_not_exist($file_absolute_path);
            if (file_put_contents($file_absolute_path, $content) === FALSE) {
                throw new RunTimeException(sprintf(t::_('The creation of file %s failed.'), $file_absolute_path));
            }
            return $file_absolute_path;
        });
        return static::get_by_absolute_path($file_absolute_file_path);
    }

    /**
     * @param string $relative_path The path where the file should be placed
     * @param UploadedFileInterface $UploadedFile
     * @return static
     */
    public static function upload_file(string $relative_path, UploadedFileInterface $UploadedFile): self
    {
        $relative_path = self::validate_relative_path($relative_path);
        //$target_path = static::get_absolute_store_path().'/'.$relative_path.'/'.$UploadedFile->getClientFilename();
        //$target_dir = dirname($target_path);
        //$relative_target_path = str_replace(static::get_absolute_store_path().'/', '', $target_path);
        //$relative_target_dir = str_replace(static::get_absolute_store_path().'/', '', $target_dir);
//        if (!file_exists($target_dir)) {
//            throw new InvalidArgumentException(sprintf(t::_('The target directory %1$s does not exist.'), $relative_target_dir));
//        }
//        if (!is_dir($target_dir)) {
//            throw new InvalidArgumentException(sprintf(t::_('The target directory %1$s is a file.'), $relative_target_dir));
//        }
//        if (!is_writeable($target_dir)) {
//            throw new InvalidArgumentException(sprintf(t::_('The target directory %1$s is not writeable.'), $relative_target_dir));
//        }
//        if (file_exists($target_path)) {
//            throw new InvalidArgumentException(sprintf(t::_('The file %1$s already exists.'), $relative_target_path));
//        }
        self::validate_file_name($UploadedFile->getClientFilename());
        //$UploadedFile->moveTo($target_path);
        $file_absolute_path = self::create_process($relative_path, function(string $real_absolute_path) use ($UploadedFile) : string
        {
            $file_absolute_path = $real_absolute_path.'/'.$UploadedFile->getClientFilename();
            self::check_file_does_not_exist($file_absolute_path);
            $UploadedFile->moveTo($file_absolute_path);
            //$file_absolute_path = $real_absolute_path.'/'.$UploadedFile->getClientFilename();
//            self::check_file_does_not_exist($file_absolute_path);
//            if (file_put_contents($file_absolute_path, $content) === FALSE) {
//                throw new RunTimeException(sprintf(t::_('The creation of file %s failed.'), $file_absolute_path));
//            }
            return $file_absolute_path;
        });
        return self::get_by_absolute_path($file_absolute_path);
    }

    /**
     * Creates a local file by downloading it from remote URL.
     * Preserved the remote filename.
     * @param string $relative_path Relative path to self::get_absolute_store_path() where the new file should be stored
     * @param string $remote_url
     * @return File
     * @throws InvalidArgumentException
     * @throws RunTimeException
     */
    public static function download_file(string $relative_path, string $remote_url): self
    {
        $relative_path = self::validate_relative_path($relative_path);
        if (!parse_url($remote_url, PHP_URL_SCHEME)) {
            throw new InvalidArgumentException(sprintf(t::_('The provided url %1$s is not a valid one (it is missing the scheme).'), $remote_url));
        }
        $new_file_name = substr($remote_url, strrpos($remote_url, '/') + 1);
        self::validate_file_name($new_file_name);
        $file_absolute_path = self::create_process($relative_path, function(string $real_absolute_path) use ($remote_url, $new_file_name) : string
        {
            $file_absolute_path = $real_absolute_path.'/'.$new_file_name;
            self::check_file_does_not_exist($file_absolute_path);
            file_put_contents($file_absolute_path, file_get_contents($remote_url));
            return $file_absolute_path;
        });
        return self::get_by_absolute_path($file_absolute_path);
    }

    /**
     * @param string $file_name
     * @throws InvalidArgumentException
     */
    private static function validate_file_name(string $file_name) : void
    {
        if (!$file_name) {
            throw new InvalidArgumentException(sprintf(t::_('No file/dir name provided.')));
        }
        if (strpos($file_name,'/') !== FALSE) {
            throw new InvalidArgumentException(sprintf(t::_('The file/dir name %1$s contains "/". This is not a valid name.'), $file_name ));
        }
        if (strpos($file_name,'..') !== FALSE) {
            throw new InvalidArgumentException(sprintf(t::_('The file/dir name %1$s contains "..". This is not a valid name.'), $file_name ));
        }
        if (!ctype_print($file_name)) {
            throw new InvalidArgumentException(sprintf(t::_('The file/dir name %1$s contains non printable characters. This is not a valid name.'), $file_name ));
        }
    }

    /**
     * Performs the creation process of a file or a dir.
     * Returns the absolute path to the newly created file or dir (the provided $Callback must return this).
     * @param string $relative_path
     * @param callable $Callback
     * @throws InvalidArgumentException
     * @throws RunTimeException
     */
    private static function create_process(string $relative_path, callable $Callback) : string
    {
        $real_store_base_path = static::get_absolute_store_path();
        $relative_path = self::validate_relative_path($relative_path);
        $absolute_path = $real_store_base_path.'/'.$relative_path;
        if (!file_exists($absolute_path)) {
            mkdir($absolute_path, 0777, true);
        }
        $real_absolute_path = realpath($absolute_path);
        if ($real_absolute_path === false) {
            throw new RunTimeException(sprintf(t::_('The relative path %1$s which is equivalent to %2$s does not exist.'), $relative_path, $absolute_path));
        }
        return $Callback($real_absolute_path);
    }

    private static function check_file_does_not_exist(string $absolute_path) : void
    {
        $relative_path = str_replace(static::get_absolute_store_path(), '', $absolute_path);
        if (file_exists($absolute_path)) {
            if (is_dir($absolute_path)) {
                throw new RunTimeException(sprintf(t::_('There is already a directory %s.'), $relative_path));
            } else {
                throw new RunTimeException(sprintf(t::_('There is already a file %s.'), $relative_path));
            }
        }
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
        $relative_path = str_replace($real_store_base_path.'/', '', $absolute_path);
        return new static($relative_path);
    }

    /**
     * Validates the provided $relative_path and returns it normalized (with leading ./).
     * @param string $relative_path
     * @return string
     * @throws InvalidArgumentException
     * @throws RunTimeException
     */
    public static function validate_relative_path(string $relative_path) : string
    {
        $real_store_base_path = static::get_absolute_store_path();
        if (!$relative_path) {
            throw new InvalidArgumentException(sprintf(t::_('There is no relative path provided.')));
        }
        if (strpos($relative_path, '..') !== FALSE) {
            throw new InvalidArgumentException(sprintf(t::_('The provided relative path %1$s contains "..". This is not allowed.'), $relative_path));
        }
        if ($relative_path[0] === '/') {
            throw new InvalidArgumentException(sprintf(t::_('The provided path %1$s is absolute. Relative path (to store base %2$s) is expected.'), $relative_path, $real_store_base_path ));
        }
        if ($relative_path[-1] === '/' && $relative_path !== './') {
            throw new InvalidArgumentException(sprintf(t::_('The provided relative path %1$s ends with "/". The provided path must not have trailing /.'), $relative_path ));
        }

        if (!ctype_print($relative_path)) {
            throw new InvalidArgumentException(sprintf(t::_('The provided relative path %1$s contains non printable characters. This is not allowed.'), $relative_path));
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