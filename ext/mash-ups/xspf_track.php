<?php
/**
 * @author Benjie Velarde
 * @copyright (c) 2009, Benjie Velarde
 * @license http://opensource.org/licenses/bsd-license.php The BSD License
 */
namespace Agilis;
/**
 * XSPF Track XML class
 */
final class XspfTrack extends XmlStruct {

    private static $children = array(
        'title',
        'localtion',
        'identifier',
        'creator',
        'annotation',
        'info',
        'image',
        'album',
        'trackNum',
        'duration',
        'link',
        'meta',
        'extension'
    );
    /**
     * Constructor.
     *
     * @param string $title Track title
     * @param string $creator The artist name
     * @param Params $config XmlStruct options
     */
    public function __construct($title='', $creator='', Params $config=NULL) {
        parent::__construct('track', $config);
        $this->title = $title;
        $this->creator = $creator;
    }
    /*
     * set value of a child node
     */
    public function __set($var, $val) {
        if (in_array($var, self::$children)) {
            $child = new XmlStruct($var, $this->_config);
            $child->addText($val);
            $this->addChild($child);
        }
    }
    /*
     * get value of a child node
     */
    public function __get($var) {
        if (in_array($var, self::$children)) {
            $child = $this->getChild($var);
            return $child->getText();
        }
    }
}
?>