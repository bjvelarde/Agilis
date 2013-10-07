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
class SimpleCaptcha extends GdImageFile {
    const PATH = 'images/captcha/';
   /**
    * @var string The captcha string
    */
    private $str;  
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
    public function __construct($uri, $options=array()) {
        parent::__construct($uri);    
        $options['html']   = isset($options['html'])   ? $options['html']   : FALSE;
        $options['strlen'] = isset($options['strlen']) ? $options['strlen'] : 5;
        $options['str_x']  = ($this->info[0] / 2) - ((10 * $options['strlen']) / 2); //isset($options['str_x'])  ? $options['str_x']  : 20;
        $options['str_y']  = ($this->info[1] / 2) - 3; //isset($options['str_y'])  ? $options['str_y']  : 10;
        $this->str = String::md5(microtime() * time())->substr(0, $options['strlen']);
        Session::getInstance()->captcha = $this->str;
        $str_color  = isset($options['strcolor']) ? $options['strcolor'] : new RgbColor(rand(0, 192), rand(0, 192), rand(0, 192));
        $line_color = isset($options['linecolor']) ? $options['linecolor'] :new RgbColor(rand(0, 255), rand(0, 255), rand(0, 255));
        $options['strcolor']  = $this->colorallocate($str_color->red, $str_color->green, $str_color->blue);
        $options['linecolor'] = $this->colorallocate($line_color->red, $line_color->green, $line_color->blue);
        $options['lines'] = isset($options['lines']) ? $options['lines'] : 5;
        $this->options  = $options;
    }
    /**
     * Render the captcha image.
     */
    public function render() {
        for ($i = 0; $i < $this->lines; $i++) {
            $x1 = rand(0, $this->info[0]);
            $x2 = rand(0, $this->info[0]);
            $y1 = rand(0, $this->info[1]);
            $y2 = rand(0, $this->info[1]);
            $this->line($x1, $y1, $x2, $y2, $this->linecolor);
        }
        $this->string(5, $this->str_x, $this->str_y, $this->str, $this->strcolor);
        if ($this->html) {
            $filename = self::PATH . $this->str . '.png';
            $this->png($filename);
            echo "<img src=\"$filename\"/>";
        } else {
            header('Content-Type: image/png'); // . $this->info['mime']);
            $this->png();
        }        
    }
    
    public function __get($var) {
        return isset($this->options[$var]) ? $this->options[$var] : parent::__get($var);
    }    
}
?>