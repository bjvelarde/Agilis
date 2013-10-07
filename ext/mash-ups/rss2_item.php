<?php
/**
 * @author Benjie Velarde
 * @copyright (c) 2009, Benjie Velarde
 * @license http://opensource.org/licenses/bsd-license.php The BSD License
 */
namespace Agilis;
/**
 * RSS 2.0 Item
 */
final class Rss2Item extends XmlStruct {
    /**
     * Xml child node tag names
     * @static
     */
    private static $children = array(
        'title', 'link', 'description',
        'author', 'category', 'comments',
        'enclosure', 'guid', 'pubDate', 'source'
    );
    /**
     * Constructor.
     *
     * @param string $title
     * @param string $link
     * @param string $desc
     * @param array $config
     */
    public function __construct($title, $link, $desc, Params $config=NULL) {
        parent::__construct('item', $config);
        $this->title = $title;
        $this->link  = $link;
        $this->description = $desc;
    }
    /*
     * set value of a child node
     */
    public function __set($var, $val) {
        if (in_array($var, self::$children)) {
            $this->removeChild($var);
            $child = new XmlStruct($var);
            if ($var == 'pubDate') {
                $val = date('r T', strtotime($val));
            }
            if (in_array($var, array('title', 'link', 'description'))) {
                $child->addCdata($val);
            } else {
                $child->addText($val);
            }
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