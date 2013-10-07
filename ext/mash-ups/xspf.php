<?php
/**
 * @author Benjie Velarde
 * @copyright (c) 2009, Benjie Velarde
 * @license http://opensource.org/licenses/bsd-license.php The BSD License
 */
namespace Agilis;
/**
 * XSPF XML Class
 */
final class Xspf extends XmlStruct {

    const VERSION = '1';
    const XMLNS = 'http://xspf.org/ns/0/';
    /**
     * Constructor
     *
     * @param Params $config XML options
     */
    public function __construct(Params $config=NULL) {
        parent::__construct('playlist', $config, array('version', 'xmlns'));
        $this->version = self::VERSION;
        $this->xmlns   = self::XMLNS;
        $this->addChild(new XmlStruct('trackList', $this->_config));
    }
    /**
     * Add trackList
     *
     * @param XspfTrack $track
     */
    public function addTrack(XspfTrack $track) { $this->trackList->addChild($track); }
    /*
     * set child values as well as the version and xmlns properties
     */
    public function __set($var, $val) {
        if ($var == 'version' || $var == 'xmlns') {
            parent::__set($var, $val);
        } else {
            $children = array(
                'title',
                'creator',
                'annotation',
                'info',
                'localtion',
                'identifier',
                'image',
                'date',
                'license',
                'attribution',
                'link',
                'meta',
                'extension'
            );
            if (in_array($var, $children)) {
                $child = new XmlStruct($var, $this->_config);
                if ($var == 'date') {
                    $val = date('c', strtotime($val));
                }
                $child->addText($val);
                $this->addChild($child);
            }
        }
    }
}
?>