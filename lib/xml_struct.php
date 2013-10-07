<?php
/**
 * @author Benjie Velarde
 * @copyright (c) 2009, Benjie Velarde
 * @license http://opensource.org/licenses/bsd-license.php The BSD License
 */
namespace Agilis;
/**
 * A DynaStruct designed for XML
 */
class XmlStruct extends DynaStruct {
    /**
     * @var string The document-element tagname
     */
    public $_tagname;
    /**#@+
     * @access protected
     */
    protected $_children;
    protected $_config;
    protected $_attriblist;
    /**#@-*/
    /**
     * Constructor
     *
     * @param string $tagname The document-element tagname
     * @param Params $config Hash of (string)enctype, (string)xmlver and (bool)ignore_blanks
     * @param array $attriblist
     */
    public function __construct($tagname='node', Params $config=NULL, array $attriblist=array()) {
        parent::__construct();
        $this->_tagname = $tagname;
        $this->_children = NULL;
        $config = $config ? $config : new Params;
        $this->_config = $config->if_empty_enctype('UTF-8')
                                ->if_empty_xmlver('1.0')
                                ->if_empty_ignore_blanks(TRUE);
        $this->_attriblist = $attriblist;
    }
    /*
     * render as XML string
     */
    public function __toString() {
        $dom = $this->toDom();
        return $dom->saveXML($dom->documentElement);
    }
    /**
     * Add a child element
     *
     * @param XmlStruct $child
     */
    public function addChild(XmlStruct $child) {
        if (!is_array($this->_children)) {
            $this->_children = array();
        }
        $this->_children[] = $child;
    }
    /**
     * Add an attribute
     *
     * @param string $attr Attribute name
     * @param mixed $val Attribute value
     */
    public function addAttribute($attr, $val) {
        if (!$this->_attriblist || in_array($this->_attriblist, $attr)) {
            $this->__set($attr, $val);
        }
    }
    /**
     * Add a text data
     *
     * @param string $string Text data
     */
    public function addText($string) { $this->_children = $string; }
    /**
     * Add a character data
     *
     * @param string $string CDATA text
     */
    public function addCdata($string) { $this->_children = 'CDATA|' . $string; }
    /**
     * Get text data
     *
     * @return string
     */
    public function getText() { return is_string($this->_children) ? $this->_children : ''; }
    /**
     * Get character data
     *
     * @return string
     */
    public function getCdata() { return is_string($this->_children) ? substr($this->_children, 6) : ''; }
    /**
     * Get child nodes
     *
     * @param string $tagname The child nodes' tagname
     * @return array
     */
    public function getChildren($tagname='') {
        if (is_array($this->_children)) {
            if ($tagname && count($this->_children) > 0) {
                $result = array();
                foreach ($this->_children as $child) {
                    if ($child->tagname == $tagname) {
                        $result[] = $child;
                    }
                }
                return $result;
            }
        }
        return $this->_children;
    }
    /**
     * Get a child element
     *
     * @param string $tagname The child's tagname
     * @return XmlStruct
     */
    public function getChild($tagname) {
        $children = $this->getChildren($tagname);
        return is_array($children) ? $children[0] : NULL;
    }
    /**
     * Remove a child element
     *
     * @param string $tagname The child's tagname
     */
    public function removeChild($tagname) {
        if (is_array($this->_children) && ($count = count($this->_children)) > 0) {
            for ($i = 0; $i < $count; $i++) {
                if ($this->_children[$i]->tagname == $tagname) {
                    unset($this->_children[$i]);
                }
            }
        }
    }
    /**
     * Convert to DomDocument object
     *
     * @return DomDocument
     */
    public function toDom() {
        $dom = $this->asCraftyArray()->toDom(
            $this->_tagname,
            'NODE',
            $this->_config->enctype,
            $this->_config->xmlver,
            $this->_config->ignore_blanks
        );
        if ($this->_children) {
            if (is_array($this->_children)) {
                foreach ($this->_children as $child) {
                    $dom->documentElement->appendChild(
                        $dom->importNode($child->toDom()->documentElement, TRUE)
                    );
                }
            } else {
                if (strpos($this->_children, 'CDATA|')) {
                    $dom->documentElement->appendChild(
                        new DomCdataSection(substr($this->_children, 6))
                    );
                } else {
                    $dom->documentElement->appendChild(
                        new DomText($this->_children, 6)
                    );
                }
            }
        }
        return $dom;
    }
}
?>