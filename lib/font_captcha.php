<?php
/**
 * @author Benjie Velarde
 * @copyright (c) 2009, Benjie Velarde
 * @license http://opensource.org/licenses/bsd-license.php The BSD License
 */
namespace Agilis;
/**
 * This class represents a captcha image file.
 */
class FontCaptcha extends GdImage {
    const PATH = 'images/captcha/';
   /**
    * @var array various options
    */
    private $options;
    /**
     * Constructor.
     *
     * @param string $uri URI of the captcha image
     * @param array $options Various render options
     * @return Captcha
     */
    public function __construct($font, $options=array()) {
        $options['font']   = $font;
        $options['html']   = isset($options['html'])   ? $options['html']   : FALSE;
        $options['strlen'] = isset($options['strlen']) ? $options['strlen'] : 4;
        $options['width']  = isset($options['width']) ? $options['width'] : 120;
        $options['height'] = isset($options['height']) ? $options['height'] : 35;
        $options['size']   = $options['height'] * 0.5;
        $this->create($options['width'], $options['height']);
        $options['str'] = String::md5(microtime() * time())->substr(0, $options['strlen']);
        //Session::getInstance()->captcha = $this->str->to_s;
        $options['lines'] = isset($options['lines']) ? $options['lines'] : 5;
        $options['spots'] = isset($options['spots']) ? $options['spots'] : 30;
        $this->options  = $options;
    }
    /**
     * Render the captcha image.
     */
    public function render() {
        $bg_color   = new RgbColor(mt_rand(200, 255), mt_rand(200, 255), mt_rand(200, 255));
        $str_color  = new RgbColor(mt_rand(0, 128), mt_rand(0, 128), mt_rand(0, 128));
        $line_color = new RgbColor(mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));
        $bg_color   = $this->colorallocate($bg_color->red, $bg_color->green, $bg_color->blue);
        $str_color  = $this->colorallocate($str_color->red, $str_color->green, $str_color->blue);
        $line_color = $this->colorallocate($line_color->red, $line_color->green, $line_color->blue);
        for ($i = 0; $i < $this->lines; $i++) {
            $x1 = mt_rand(0, $this->width);
            $x2 = mt_rand(0, $this->width);
            $y1 = mt_rand(0, $this->height);
            $y2 = mt_rand(0, $this->height);
            $this->line($x1, $y1, $x2, $y2, $line_color);
        }
        for ($i = 0; $i < $this->spots; $i++) {
            $spot_color = new RgbColor(mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));
            $spot_color = $this->colorallocate($spot_color->red, $spot_color->green, $spot_color->blue);
            $this->filledellipse(mt_rand(0, $this->width), mt_rand(0, $this->height), mt_rand(1, 5), mt_rand(1, 5), $spot_color);
        }
        $angle = mt_rand(0, 20);
        $textbox = $this->ttfbbox($this->size, $angle, $this->font, $this->str);
		$x = ($this->width - $textbox[4]) / 2;
		$y = ($this->height - $textbox[5]) / 2;
        $this->ttftext($this->size, $angle, $x, $y, $str_color, $this->font, $this->str);
        Session::getInstance()->captcha = $this->str->to_s;
        if ($this->html) {
            $filename = self::PATH . $this->str . '.png';
            $this->png($filename);
            echo "<img src=\"$filename\"/>";
        } else {
            header('Content-Type: image/png');
            $this->png();
        }
    }

    public function __get($var) {
        return isset($this->options[$var]) ? $this->options[$var] : parent::__get($var);
    }

    public static function isCorrect($name='captcha') {
        return (isset($_POST[$name]) && $_POST[$name] == Session::getInstance()->captcha);
    }
}
?>