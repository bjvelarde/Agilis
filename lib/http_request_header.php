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
 * HTTP Request Header Class
 */
class HttpRequestHeader extends HttpHeader {
    /**
     * Constructor.
     *
     * @param Params $headers
     */
    public function __construct(Params $headers=NULL) {
        parent::__construct(
            'accept',
            'accept_charset',
            'accept_encoding',
            'accept_language',
            'accept_ranges',
            'authorization',
            'cache_control',
            'connection',
            'cookie',
            'content_length',
            'content_type',
            'date',
            'expect',
            'from',
            'host',
            'if_match',
            'if_modified_since',
            'if_none_match',
            'if_range',
            'if_unmodified_since',
            'max_forwards',
            'pragma',
            'proxy_authorization',
            'range',
            'referer',
            'te',
            'upgrade',
            'user_agent',
            'via',
            'warn'
        );
        $headers = $headers ? $headers : new Params;
        $this->initValues($headers);
    }
    /**
     * Render as string
     */
    public function __toString() {
        if ($this['content_type']) {
            $type = $this['content_type'];
            unset($this['content_type']);
        }
        if ($this['content_length']) {
            $len = $this['content_length'];
            unset($this['content_length']);
        }
        $str = parent::__toString();
        if ($type) {
            $str .= 'Content-Type: ' . $type . "\n";
        }
        if ($len) {
            $str .= 'Content-Length: ' . $len . "\n";
        }
        return $str;
    }
    /**
     * Convert object property to http header key.
     *
     * @param string $key
     * @return string
     */
    protected function convertKey($key) {
        if ($key == 'te') { return 'TE'; }
        return parent::convertKey($key);
    }

}
?>