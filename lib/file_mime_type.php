<?php
/**
 * @author Benjie Velarde
 * @copyright (c) 2013, Benjie Velarde
 * @license http://opensource.org/licenses/bsd-license.php The BSD License
 */
namespace Agilis;

use \Exception as Exception;
use \finfo as finfo;
/**
 * A Class to get mime type of a given file
 */
class FileMimeType {
    /**
     * @var string The file URI
     */
    private $uri;
    /**
     * Constructor.
     *
     * @param string $uri The file URI
     */
    public function __construct($uri){
        if (!file_exists($uri)) {
            throw new Exception($uri . ' does not exists.');
        }
        $this->uri = realpath($uri);
    }
    /*
     * render as a mime-type string
     */
    public function __toString() {
        if (class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $ret = $finfo->file($this->uri);
            }
        } elseif (function_exists('mime_content_type')) {
            $ret = mime_content_type($this->uri);
        }
        return $ret ? $ret : $this->guessByExtension();
    }

    public function guessByExtension() {
        $ext = strtolower(pathinfo($this->uri, PATHINFO_EXTENSION));
        $mimes = array(
            'hqx'   => 'application/mac-binhex40',
            'cpt'   => 'application/mac-compactpro',
            'doc'   => 'application/msword',
            'bin'   => 'application/macbinary',
            'dms'   => 'application/octet-stream',
            'lha'   => 'application/octet-stream',
            'lzh'   => 'application/octet-stream',
            'exe'   => 'application/octet-stream',
            'class' => 'application/octet-stream',
            'psd'   => 'application/octet-stream',
            'so'    => 'application/octet-stream',
            'sea'   => 'application/octet-stream',
            'dll'   => 'application/octet-stream',
            'oda'   => 'application/oda',
            'pdf'   => 'application/pdf',
            'ai'    => 'application/postscript',
            'eps'   => 'application/postscript',
            'ps'    => 'application/postscript',
            'smi'   => 'application/smil',
            'smil'  => 'application/smil',
            'mif'   => 'application/vnd.mif',
            'xls'   => 'application/vnd.ms-excel',
            'ppt'   => 'application/vnd.ms-powerpoint',
            'wbxml' => 'application/vnd.wap.wbxml',
            'wmlc'  => 'application/vnd.wap.wmlc',
            'dcr'   => 'application/x-director',
            'dir'   => 'application/x-director',
            'dxr'   => 'application/x-director',
            'dvi'   => 'application/x-dvi',
            'gtar'  => 'application/x-gtar',
            'php'   => 'application/x-httpd-php',
            'php4'  => 'application/x-httpd-php',
            'php3'  => 'application/x-httpd-php',
            'phtml' => 'application/x-httpd-php',
            'phps'  => 'application/x-httpd-php-source',
            'js'    => 'application/x-javascript',
            'swf'   => 'application/x-shockwave-flash',
            'sit'   => 'application/x-stuffit',
            'tar'   => 'application/x-tar',
            'tgz'   => 'application/x-tar',
            'xhtml' => 'application/xhtml+xml',
            'xht'   => 'application/xhtml+xml',
            'zip'   => 'application/zip',
            'mid'   => 'audio/midi',
            'midi'  => 'audio/midi',
            'mpga'  => 'audio/mpeg',
            'mp2'   => 'audio/mpeg',
            'mp3'   => 'audio/mpeg',
            'aif'   => 'audio/x-aiff',
            'aiff'  => 'audio/x-aiff',
            'aifc'  => 'audio/x-aiff',
            'ram'   => 'audio/x-pn-realaudio',
            'rm'    => 'audio/x-pn-realaudio',
            'rpm'   => 'audio/x-pn-realaudio-plugin',
            'ra'    => 'audio/x-realaudio',
            'rv'    => 'video/vnd.rn-realvideo',
            'wav'   => 'audio/x-wav',
            'bmp'   => 'image/bmp',
            'gif'   => 'image/gif',
            'jpeg'  => 'image/jpeg',
            'jpg'   => 'image/jpeg',
            'jpe'   => 'image/jpeg',
            'png'   => 'image/png',
            'tiff'  => 'image/tiff',
            'tif'   => 'image/tiff',
            'css'   => 'text/css',
            'html'  => 'text/html',
            'htm'   => 'text/html',
            'shtml' => 'text/html',
            'txt'   => 'text/plain',
            'text'  => 'text/plain',
            'log'   => 'text/plain',
            'rtx'   => 'text/richtext',
            'rtf'   => 'text/rtf',
            'xml'   => 'text/xml',
            'xsl'   => 'text/xml',
            'mpeg'  => 'video/mpeg',
            'mpg'   => 'video/mpeg',
            'mpe'   => 'video/mpeg',
            'qt'    => 'video/quicktime',
            'mov'   => 'video/quicktime',
            'avi'   => 'video/x-msvideo',
            'movie' => 'video/x-sgi-movie',
            'doc'   => 'application/msword',
            'word'  => 'application/msword',
            'xl'    => 'application/excel',
            'eml'   => 'message/rfc822'
        );
        return (!isset($mimes[$ext])) ? 'application/octet-stream' : $mimes[$ext];
    }

    public static function get($uri) {
        $self = new self($uri);
        return "$self";
    }
}
?>