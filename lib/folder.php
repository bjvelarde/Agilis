<?php
/**
 * @package Agilis
 * @version 1.0
 * @author Benjie Velarde
 * @copyright (c) 2013, Benjie Velarde bvelarde@gmail.com
 * @license http://opensource.org/licenses/PHP-3.0
 */
namespace Agilis;
/**
 * A class to wrap the 'dir' object and some primitive directory functions.
 *
 * @method array scan() scan([int $sorting_order [, resource $context ]]) Alias of the primitive scandir().
 */
class Folder {
    /**
     * @var resource
     */
    private $dir;
    /**
     * Constructor.
     *
     * @param string $path The directory path
     * @param bool $create Attemp to create directory if set to TRUE
     * @throws Exception
     */
    public function __construct($path, $create=TRUE) {
        if (!self::exists($path)) {
            if ($create) {
                self::make($path);
            } else {
                throw new FolderException($path . ' is not a directory.');
            }
        }
        if (($this->dir = dir($path)) === FALSE) {
            throw new FolderException('Failed to open directory: ' . $path);
        }
    }
    /*
     * Overload primitive directory functions and 'dir' methods
     */
    public function __call($method, $args) {
        if ($method == 'scan') {
            array_unshift($args, $this->dir->path);
            return call_user_func_array('scandir', $args);
        }
        return call_user_func_array(array($this->dir, $method), $args);
    }
    /**
     * Check if a path given is a directory.
     *
     * @param string $path The path in question.
     * @return bool
     */
    public static function exists($path) { return (file_exists($path) && is_dir($path)); }
    /**
     * Attemp to create a directory by invoking the primitive function mkdir().
     *
     * @throws Exception
     * @return bool
     */
    public static function make() {
        $args = func_get_args();
        $result = call_user_func_array('mkdir', $args);
        if (!$result) {
            throw new FolderException('Failed to create directory: ' . $args[0]);
        } else {
            return $result;
        }
    }
}

class FolderException extends \Exception {}
?>