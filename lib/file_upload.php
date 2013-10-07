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
 * A class for URL-encoded file upload from $_FILES
 */
class FileUpload {
    /**
     * @var array A reference to $_FILES
     */
    protected $instance;
    /**
     * @var string Destination path
     */
    protected $path;
    /**
     * @var string Destination file name
     */
    protected $destfile;
    /**
     * @var bool Required upload or not
     */
    protected $required;
    /**
     * @var array List of mime-types where we can validate the mime-type of this upload against.
     */
    protected $filters;
    /**
     * Constructor.
     *
     * @param Params $params Hash with the ff keys: name, required, filters, path and destfile
     */
    public function __construct(Params $params) {
        $params->checkRequired('name');
        $name = trim($params->name);
        $this->instance =& $_FILES[$name];
        $this->required = $params->required === TRUE ? TRUE : FALSE;
        if (($error = $this->getError()) !== NULL) {
            throw new FileUploadException($error);
        }
        $this->filters = is_array($params->filters) ? $params->filters :
                         ($params->filters ? array($params->filters) : NULL);
        if (!$this->isValid()) {
            throw new FileUploadException('file of type ' . $this->type . ' is not allowed');
        }
        $params->if_empty_path('./');
        $this->setPath($params->path);        
        $this->setDestFile($params->destfile);
    }
    /*
     * magic getter
     */
    public function __get($var) {
        if ($var == 'path' || $var == 'destfile') {
            return $this->{$var};
        } else {
            return (isset($this->instance[$var])) ? $this->instance[$var] : NULL;
        }
    }
    /*
     * magic setter
     */
    public function __set($var, $val) {
        switch ($var) {
            case 'path'    : $this->setPath($val); break;
            case 'destfile': $this->setDestFile($val); break;
        }
    }
    /**
     * Upload the file.
     *
     * @return bool
     */
    public function move() {        
        return move_uploaded_file($this->tmp_name, $this->path . $this->destfile);
    }    
    /**
     * Set the destination file name.
     *
     * @param string $destfile The destination file name
     */
    public function setDestFile($destfile) {
        if ($destfile && is_string($destfile)) {
            if (strstr($destfile, '.')) { // remove file extension if present
                $parts = explode('.', $destfile);
                array_pop($parts);
                $destfile = implode('.', $parts);
            }
            $parts = explode('.', $this->name);
            $ext = array_pop($parts);
            $this->destfile = "{$destfile}.{$ext}";
        } else {
            $this->destfile = $this->name;
		}
    }    
    /**
     * Set the destination path.
     *
     * @param string $path The destination path
     * @throws FileUploadException on failure to create the destination path
     */
    public function setPath($path) {
        $path = trim($path);
        $this->path = $path . (substr($path, -1) == '/' ? '' : '/');
        if (!file_exists($path) && !@mkdir($path, 0777, TRUE)) {
            throw new FileUploadException('failed to create upload path: ' . $this->path);
        }
    }
    /**
     * Match the uploaded file's mime-type against the list of accepted mime-types
     *
     * @return bool
     */
    protected function isValid() {
        if (!$this->filters) {
            return TRUE;
        } else {
            return in_array($this->type, $this->filters);
        }
    }    
    /**
     * Get the error string based on the error code.
     *
     * @return string|NULL The error string or NULL if no errors.
     */
    private function getError() {
        switch ($this->error) {
            case UPLOAD_ERR_INI_SIZE  : return 'The uploaded file exceeds the upload_max_filesize directive in php.ini';
            case UPLOAD_ERR_FORM_SIZE : return 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form';
            case UPLOAD_ERR_PARTIAL   : return 'The uploaded file was only partially uploaded';
            case UPLOAD_ERR_NO_TMP_DIR: return 'Missing a temporary folder';
            case UPLOAD_ERR_CANT_WRITE: return 'Failed to write file to disk';
            case UPLOAD_ERR_EXTENSION : return 'File upload stopped by extension';
            case UPLOAD_ERR_NO_FILE   : return $this->required ? 'No file was uploaded' : NULL;
        }
        return NULL;
    }
    
}

class FileUploadException extends \Exception {}
?>