<?php
/**
 * @author Benjie Velarde
 * @copyright (c) 2009, Benjie Velarde
 * @license http://opensource.org/licenses/bsd-license.php The BSD License
 */
namespace Agilis;

final class Rss1 extends XmlStruct {

    private $_items;

    public function __construct($title, $link, $desc, $about_url, Params $config=NULL) {
        parent::__construct('channel', $config, array('xmlns', 'xmlns:rdf', 'rdf:about'));
        $this['xmlns'] = 'http://purl.org/net/rss1.1#';
        $this['xmlns:rdf'] = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';
        $this['rdf:about'] = $about_url;
        $this->_items = new XmlStruct('items', $this->_config, array('rdf:parseType'));
        $this->_items['rdf:parseType'] = 'Collection';
        $this->title = $title;
        $this->link  = $link;
        $this->description = $desc;
    }

    public function __set($var, $val) {
        if (in_array($var, array('title', 'link', 'description'))) {
            $this->removeChild($var);
            $child = new XmlStruct($var);
            $child->addText($val);
            $this->addChild($child);
        } else {
            parent::__set($var, $val);
        }
    }

    public function __get($var) {
        if (in_array($var, array('title', 'link', 'description'))) {
            $child = $this->getChild($var);
            return $child->getText();
        }
        return parent::__get($var);
    }

    public function toDom() {
        $this->addChild($this->_items);
        return parent::toDom();
    }

    public function addImage($title, $url) {
        $this->removeChild('image');
        $image = new XmlStruct('image', $this->_config, array('rdf:parseType'));
        $image['rdf:parseType'] = 'Resource';
        $t = new XmlStruct('title');
        $u = new XmlStruct('url');
        $t->addText($title);
        $u->addText($url);
        $image->addChild($t);
        $image->addChild($u);
        $this->addChild($image);
    }

    public function addItem($title, $link, $desc, $about_url) {
        $item = new XmlStruct('item', $this->_config, array('rdf:about'));
        $item['rdf:about'] = $about_url;
        $t = new XmlStruct('title');
        $l = new XmlStruct('link');
        $d = new XmlStruct('description');
        $t->addText($title);
        $l->addText($link);
        $d->addText($description);
        $item->addChild($t);
        $item->addChild($l);
        $item->addChild($d);
        $this->_items->addChild($item);
    }

    public function addOthers(XmlStruct $struct) {
        $others = new XmlStruct('others', $this->_config);
        $others->addChild($struct);
        $this->addChild($others);
    }
}
?>