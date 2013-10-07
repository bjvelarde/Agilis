<?php
/**
 * @author Benjie Velarde
 * @copyright (c) 2009, Benjie Velarde
 * @license http://opensource.org/licenses/bsd-license.php The BSD License
 */
namespace Agilis;

/**
 * Class representing a RGB Color
 */
class RgbColor extends FixedStruct {
    /**
     * Constructor
     *
     * @param int $red 0 - 255
     * @param int $green 0 - 255
     * @param int $blue 0 - 255
     */
    public function __construct($red, $green, $blue) {
        parent::__construct('red', 'green', 'blue');
        $this->red   = $red;
        $this->green = $green;
        $this->blue  = $blue;
    }
    /**
     * Convert the class to array
     *
     * @return array
     */
    public function asArray() { return $this->_elements; }
    /*
     * Render as html entity string in #hex; format
     */
    public function __toString() {
        return '#' . dechex($this->red) . dechex($this->green) . dechex($this->blue);
    }
}
?>