<?php
/**
 * @author Benjie Velarde
 * @copyright (c) 2009, Benjie Velarde
 * @license http://opensource.org/licenses/bsd-license.php The BSD License
 */
namespace Agilis;

final class Rss2 extends XmlStruct {

    private $_channel;

    private static $children = array(
        'title', 'link', 'description',
        'pubDate', 'language', 'copyright',
        'category', 'cloud', 'docs',
        'generator', 'lastBuildDate', 'managingEditor',
        'rating', 'skipDays', 'skipHours',
        'textInput', 'ttl', 'webMaster'
    );

    public function __construct($title, $link, $desc, Params $config=NULL) {
        parent::__construct('rss', $config, array('version'));
        $this->_channel = new XmlStruct('channel', $config);
        $this->version = '2.0';
        $this->title = $title;
        $this->link  = $link;
        $this->description = $desc;
    }

    public function __set($var, $val) {
        if (in_array($var, self::$children)) {
            $this->removeChild($var);
            $child = new XmlStruct($var);
            if ($var == 'pubDate' || $var == 'lastBuildDate') {
                $val = date('r T', strtotime($val));
            }
            $child->addText($val);
            $this->_channel->addChild($child);
        } else {
            parent::__set($var, $val);
        }
    }

    public function __get($var) {
        if (in_array($var, self::$children)) {
            $child = $this->_channel->getChild($var);
            return $child->getText();
        } elseif ($var == 'items') {
            return $this->_channel->getChildren('item');
        }
        return parent::__get($var);
    }

    public function toDom() {
        $this->addChild($this->_channel);
        return parent::toDom();
    }

    public function addImage($title, $url, $link) {
        $this->removeChild('image');
        $image = new XmlStruct('image', $this->_config);
        $t = new XmlStruct('title');
        $u = new XmlStruct('url');
        $l = new XmlStruct('link');
        $t->addText($title);
        $u->addText($url);
        $l->addText($link);
        $image->addChild($t);
        $image->addChild($u);
        $image->addChild($l);
        $this->_channel->addChild($image);
    }

    public function addItem(Rss2Item $item) { $this->_channel->addChild($item); }
}
?>