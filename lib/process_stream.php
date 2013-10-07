<?php
/**
 * @author Benjie Velarde
 * @copyright (c) 2013, Benjie Velarde
 * @license http://opensource.org/licenses/bsd-license.php The BSD License
 */
namespace Agilis;

/**
 * Wrapper class for process stream operations.
 */
class ProcessStream extends Stream {
    /*
     * Constructor.
     */
    public function __construct() {
        parent::__construct();
        $args = func_get_args();
        if (($this->stream = call_user_func_array('popen', $args)) === FALSE) {
            switch ($args[1]) {
                case 'w': case 'w+': $mode = 'writing'; break;
                case 'r': case 'r+': $mode = 'reading'; break;
                case 'a': $mode = 'appending'; break;
            }
            throw new ProcessStreamException($args[0], $mode);
        }
    }
    
    public function __destruct() { if (is_resource($this->stream)) { pclose($this->stream); } }
}

class ProcessStreamException extends \Exception {
    
    public function __construct($file, $mode) {
        parent::__construct("Failed to open file: $file for $mode");
    }
}
?> 