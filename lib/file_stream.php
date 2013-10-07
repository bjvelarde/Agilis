<?php
/**
 * @author Benjie Velarde
 * @copyright (c) 2013, Benjie Velarde
 * @license http://opensource.org/licenses/bsd-license.php The BSD License
 */
namespace Agilis;
/**
 * Wrapper class for file stream operations.
 */
class FileStream extends Stream {
    /*
     * Constructor.
     */
    public function __construct() {
        parent::__construct();
        $args = func_get_args();
        if ($args) {
            if (($this->stream = call_user_func_array('fopen', $args)) === FALSE) {
                switch ($args[1]) {
                    case 'w': case 'w+': $mode = 'writing'; break;
                    case 'r': case 'r+': $mode = 'reading'; break;
                    case 'a': $mode = 'appending'; break;
                }
                throw new FileStreamException($args[0], $mode);
            }
        } else {
        	$this->stream = tmpfile();
        }
    }
}

class FileStreamException extends \Exception {
    
    public function __construct($file, $mode) {
        parent::__construct("Failed to open file: $file for $mode");
    }
}
?> 