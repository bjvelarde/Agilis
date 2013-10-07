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
 * A wrapper class for most common stream operations using a stream handle
 */
abstract class Stream {
    /**
     * @var resource
     */
    protected $stream;

    public function __construct() { $this->stream = NULL; }

    public function __destruct() { if (is_resource($this->stream)) { $this->close(); } }
    /*
     * Overload built-in stream functions.
     */
    public function __call($method, $args) {
        // ignore calls to fopen and fsockopen
        if (!in_array($method, array('open', 'sockopen'))) {
            if ($method != 'rewind') {
                $function = function_exists('stream_' . $method) ? 'stream_' . $method :
                            (function_exists('f' . $method) ? 'f' . $method : NULL);
            } else {
                $function = $method;
            }
            if ($function) {
                if ($this->methodNeedsHandle($function, $args)) {
                    array_unshift($args, $this->stream);
                }
                if ($function == 'ftruncate') {
                    $stat = $this->stat();
                    $size = $args[1];
                    if ($stat['size'] > $size) {
                        return call_user_func_array($function, $args);
                    } else {
                        return FALSE;
                    }
                } else {
                    return call_user_func_array($function, $args);
                }
            }
        }
        return FALSE;
    }
    /**
     * Check if a method call needs the stream handle as first param.
     *
     * @access private
     * @param string $method
     * @param mixed $args
     * @return bool
     */
    private function methodNeedsHandle($method, $args) {
        if ($method == 'stream_context_get_options') {
            return $args ? FALSE : TRUE;
        } else {
            $parts = explode('_', $method);
            if ($parts[0] == 'stream') {
                $needs_handle = array(
                    'stream_copy_to_stream',
                    'stream_encoding',
                    'stream_filter_append',
                    'stream_filter_prepend',
                    'stream_get_contents',
                    'stream_get_line',
                    'stream_get_meta_data',
                    'stream_set_blocking',
                    'stream_set_timeout',
                    'stream_set_write_buffer',
                    'stream_socket_accept',
                    'stream_socket_enable_crypto',
                    'stream_socket_get_name',
                    'stream_socket_recvfrom',
                    'stream_socket_sendto',
                    'stream_socket_shutdown'
                );
                return in_array($method, $needs_handle) ? TRUE : FALSE;
            }
            return TRUE;
        }
    }
}
?>
