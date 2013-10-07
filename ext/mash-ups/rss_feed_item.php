<?php
/**
 * @author Benjie Velarde
 * @copyright (c) 2009, Benjie Velarde
 * @license http://opensource.org/licenses/bsd-license.php The BSD License
 */
namespace Agilis;

use \DomElement as DomElement;
/**
 * RSS Feed Item
 */
class RssFeedItem extends FixedStruct {
    /*
     * Constructor
     */
    public function __construct() {
        parent::__construct('title', 'link', 'description', 'pubDate', 'guid');
    }
    /**
     * Parse a DomElement item
     *
     * @static
     * @param DomElement $item
     * @return RssFeedItem
     */
    public static function read(DomElement $item) {
        $rss_item = new self();
        $rss_item->title       = $item->getElementsByTagName('title')->item(0)->nodeValue;
        $rss_item->link        = $item->getElementsByTagName('link')->item(0)->nodeValue;
        $rss_item->description = $item->getElementsByTagName('description')->item(0)->nodeValue;
        $rss_item->pubDate     = $item->getElementsByTagName('pubDate')->item(0)->nodeValue;
        $rss_item->guid        = $item->getElementsByTagName('guid')->item(0)->nodeValue;
        return $rss_item;
    }
}
?>