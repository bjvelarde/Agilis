<?php
/**
 * @author Benjie Velarde
 * @copyright (c) 2013, Benjie Velarde
 * @license http://opensource.org/licenses/bsd-license.php The BSD License
 */
namespace Agilis;
/**
 * This class represents a GD image file.
 */
class GdImageFile extends GdImage {
    /**
     * @var string
     */
    protected $uri;
    
    protected $info;
    /**
     * Constructor.
     *
     * @param string $uri The image uri
     */
    public function __construct($uri) {
        if (($info = getimagesize($uri)) === FALSE) {
            throw new ConstructorException(__CLASS__, $uri . ' is not a valid image.');
        }
        list($width, $height, $type) = $info;
        $this->info = $info;
        $copyer = $this->getCopyer($type);
        $this->{$copyer}($uri);
    }
    /**
     * Get the copyer function
     *
     * @param int $type
     * @return string
     */
    private function getCopyer($type) {
        switch ($type) {
            case IMAGETYPE_JPEG: return 'createfromjpeg';
            case IMAGETYPE_GIF : return 'createfromgif';
            case IMAGETYPE_PNG : return 'createfrompng';
            case IMAGETYPE_WBMP: return 'createfromwbmp';
        }
    }
}
?>