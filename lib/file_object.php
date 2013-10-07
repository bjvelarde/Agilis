<?php
/**
 * @author Benjie Velarde
 * @copyright (c) 2009, Benjie Velarde
 * @license http://opensource.org/licenses/bsd-license.php The BSD License
 */
namespace Agilis;
/**
 * This class combines the functionalities of native non-stream and native stream file handling
 */
class FileObject {
    /**
     * a boolean constant to be passed as second arg of openStream
     * to tell whether this is an ordinary file or a process.
     */
	const PROCESS = TRUE;
    /**
     * @var resource
     */
    protected $stream;
    /**#@+
     * @var string
     */    
    protected $stream_mode;
    protected $file;
    /**#@-*/
    /**
     * Constructor.
     *
     * @param string $file The file URI
     */
    public function __construct($file) {
        $this->file = $file;
        $this->stream = NULL;
        $this->stream_mode = 'w';
    }
    /**
     * Open a stream to the file
     *
     * @param string $mode
     * @param bool $is_process
     */
    public function openStream($mode='w', $is_process=FALSE) {
        if (!$this->stream || ($this->stream && $this->stream_mode != $mode)) {
            $this->stream_mode = $mode;
            $stream_class = $is_process ? 'ProcessStream' : 'FileStream';
            $this->stream = new $stream_class($this->file, $this->stream_mode);
        }
    }
    /*
     * allow native file functions to be called as class methods
     */
    public function __call($method, $args) {
    	if ($this->stream intanceof Stream) {
    	    $result = call_user_func_array(array($this->stream, $method), $args);
    	    if ($result !== FALSE) { return $result; }
    	}
        $method = ($method == 'load') ? 'file' : $method;
        $method = ($method == 'read') ? 'readfile' : $method;
        if (in_array($method, array(
            'basename', 'chgrp', 'chmod', 'chown', 'copy', 'dirname', 'file', 'is_dir',
            'is_executable', 'is_file', 'is_link', 'is_readable', 'is_uploaded_file',
            'is_writable', 'lchgrp', 'lchown', 'link', 'linkinfo', 'lstat', 'pathinfo',
            'readfile', 'readlink', 'realpath', 'rename', 'stat', 'symlink', 'touch', 'unlink'
        ))) {
        	array_unshift($args, $this->file);
        } elseif (in_array($method, array('exists', 'get_contents', 'put_contents'))) {
        	$method = 'file_' . $method;
        	array_unshift($args, $this->file);
        } elseif (in_array($method, array(
            'atime', 'ctime', 'group', 'inode', 'mtime', 'owner', 'perms', 'size', 'type'
        ))) {
        	$method = 'file' . $method;
        	array_unshift($args, $this->file);
        } elseif ($method == 'clearstatcache') {
        	array_push($args, $this->file);
        } elseif ($method == 'fnmatch') {
        	$pattern = array_shift($args);
        	array_unshift($args, $this->file);
        	array_unshift($args, $pattern);
        }
        if (function_exists($method)) {
        	return call_user_func_array($method, $args);
        }
    }
}
?>