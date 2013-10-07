<?php
/**
 * @author Benjie Velarde
 * @copyright (c) 2013, Benjie Velarde
 * @license http://opensource.org/licenses/bsd-license.php The BSD License
 */
namespace Agilis;

use \Exception as Exception;
/**
 * Image Resizer Class
 */
class GdImageResizer {
    /**
     * @var string source image uri
     */
    private $source;
    /**
     * @var string destination image uri
     */
    private $destination;
    /**
     * Constructor
     *
     * @param Params $params
     */
    public function __construct(Params $params) {
        $params->checkRequired('src');
        $src = $params->src;
        $src->checkRequired('file');
        $src->if_empty_path('images');
        $src->path($src->path . (substr($src->path, -1) == '/' ? '' : '/'));
        $src_uri = $src->path . $src->file;
        if (!file_exists($src_uri) && !getimagesize($src_uri)) {
            throw new Exception('source image (' . $src_uri . ') does not exists.');
        }
        list($src_width, $src_height, $src_type) = getimagesize($src_uri);
        if (!$this->isSupported($src_type)) {
            throw new Exception('image type is not supported.');
        }
        $src = $src->width($src_width)
                   ->height($src_height)
                   ->type($src_type);
        $this->source = $src; //new DynaStruct($src);
        $dest = $params->dest;
        $dest->if_empty_path($dest->path, $this->source->path);
        $dest->if_empty_file($dest->file, $this->source->file);
        if ($dest->percent) {
            $percent = $dest->percent / 100;
            $dest = $dest->width($this->source->width * $percent)
                         ->height($this->source->height * $percent);
        } else {
            $dest->if_empty_width($this->source->width);
            $dest->if_empty_height($this->source->height);
        }
        $dest->if_empty_type($this->source->type);
        $dest->canvass = (isset($dest->canvass) && $dest->canvass instanceof RgbColor) ?
                         $dest->canvass : new RgbColor(255, 255, 255);
        $this->destination = $dest;
    }
    /**
     * Check if image type is supported.
     *
     * @param int $imagetype
     * @return bool
     */
    private function isSupported($imagetype) {
        return in_array(
            $imagetype,
            array(
                IMAGETYPE_GIF,
                IMAGETYPE_JPEG,
                IMAGETYPE_PNG,
                IMAGETYPE_WBMP
            )
        );
    }
    /**
     * Get the resampler struct
     *
     * @return DynaStruct
     */
    private function getResampler() {
        $resampler = new DynaStruct();
        $resampler->creator = 'createtruecolor';
        switch ($this->destination->type) {
            case IMAGETYPE_JPEG:
                $resampler->imager    = 'jpeg';
                break;
            case IMAGETYPE_GIF :
			    //if (imagetypes() & IMG_GIF) {
                //    $resampler->creator   = 'imagecreate';
                //    $resampler->imager    = 'gif';
				//} else {
				    $resampler->imager    = 'png';
				//}
                break;
            case IMAGETYPE_PNG :
                $resampler->imager    = 'png';
                break;
            case IMAGETYPE_WBMP:
                $resampler->imager    = 'wbmp';
                break;
        }
        return $resampler;
    }
    /**
     * Resize the image
     */
    public function resize() {
        $resampler   = $this->getResampler();
        $destination = $this->destination->path . $this->destination->file;
        if ($this->source->width < $this->destination->width && $this->source->height < $this->destination->height) {
            $new_width  = $this->destination->width;
            $new_height = $this->destination->height;
        } else {
            if ($this->source->width > $this->source->height) {
                $new_width  = $this->destination->width;
                $new_height = intval($this->source->height * ($new_width / $this->source->width));
            } else {
                $new_height = $this->destination->height;
                $new_width  = intval($this->source->width * ($new_height / $this->source->height));
            }
        }
        $startx  = ($this->destination->width  - $new_width)  / 2;
        $starty  = ($this->destination->height - $new_height) / 2;
        $source_im = new GdImageFile($this->source->path . $this->source->file);
        $dest_im = new GdImage();       
        $dest_im->{$resampler->creator}($this->destination->width, $this->destination->height);
        $canvass = $dest_im->colorallocate(
            $this->destination->canvass->red,
            $this->destination->canvass->green,
            $this->destination->canvass->blue
        );
        $dest_im->fill(0, 0, $canvass);
        $dest_im->copyresampled($source_im, $startx, $starty, 0, 0, $new_width, $new_height, $this->source->width, $this->source->height);
        $result = $dest_im->{$resampler->imager}($destination);
        $dest_im->colordeallocate($canvass);
		return $result;
    }
}
?>