<?php
/**
 * @author Benjie Velarde
 * @copyright (c) 2009, Benjie Velarde
 * @license http://opensource.org/licenses/bsd-license.php The BSD License
 */
namespace Agilis;

use \DomDocument as DomDocument;
/**
 * RSS Feed
 */
class RssFeed extends FixedStruct {
    /*
     * RSS feed validator URL
     */
    const VALIDATOR_URL = 'http://feedvalidator.org/check.cgi?url=';
    /*
     * Constructor
     */
    public function __construct() {
        parent::__construct(
            'title', 'link', 'description',
            'language', 'pubDate', 'lastBuildDate',
            'docs', 'generator', 'managingEditor',
            'webMaster', 'ttl', 'items'
        );
        $this->items = array();
    }
    /**
     * Add a feed item
     *
     * @param RssFeedItem $item
     */
    public function addItem(RssFeedItem $item) {
        $this->items[] = $item;
    }
    /**
     * Read a feed
     *
     * @param string $url
     * @throws RssValidationException
     * @return RssFeed
     */
    public static function read($url) {
        $error = self::validate($url);
        if ($error === NULL) {
            $dom = new DomDocument();
            $dom->loadXML(file_get_contents($url));
            $rss = new self();
            $rss->channel        = $dom->getElementsByTagName('channel')->item(0)->nodeValue;
            $rss->title          = $channel->getElementsByTagName('title')->item(0)->nodeValue;
            $rss->link           = $channel->getElementsByTagName('link')->item(0)->nodeValue;
            $rss->description    = $channel->getElementsByTagName('description')->item(0)->nodeValue;
            $rss->language       = $channel->getElementsByTagName('language')->item(0)->nodeValue;
            $rss->pubDate        = $channel->getElementsByTagName('pubDate')->item(0)->nodeValue;
            $rss->lastBuildDate  = $channel->getElementsByTagName('lastBuildDate')->item(0)->nodeValue;
            $rss->docs           = $channel->getElementsByTagName('docs')->item(0)->nodeValue;
            $rss->generator      = $channel->getElementsByTagName('generator')->item(0)->nodeValue;
            $rss->managingEditor = $channel->getElementsByTagName('managingEditor')->item(0)->nodeValue;
            $rss->webMaster      = $channel->getElementsByTagName('webMaster')->item(0)->nodeValue;
            $rss->ttl            = $channel->getElementsByTagName('ttl')->item(0)->nodeValue;
            $items = $channel->getElementsByTagName('items');
            foreach ($items as $item) {
                $rss->addItem(RssFeedItem::read($item));
            }
            return $rss;
        } else {
            throw new RssValidationException($error);
        }
    }
    /**
     * Validate a feed
     *
     * @param string $url
     * @return string
     */
    public static function validate($url) {
        if (!preg_match("@^http://@si", $url)) { $url = 'http://' . $url; }
        $response = self::getValidatorResponse($url);
        if ($response && !preg_match("@sorry@si", $response)) {
            return NULL;
        }
        return $response;
    }
   /**
     * Get Validator response
     *
     * @param string $url
     * @return string
     */
    private static function getValidatorResponse($url) {
        $url = self::VALIDATOR_URL . $url;
        $response = '';
        try {
            $fs = new FileStream($url, 'r');
            while (!$fs->eof()) {
                $response .= $fs->gets(4096);
            }
            $fs->close();
        } catch (FileStreamException $e) {
            $url_parsed = parse_url($url);
            $host = $url_parsed['host'];
            $try_curl = FALSE;
            if ($host) {
                $port  = Common::ifEmpty($url_parsed['port'], 80);
                $path  = Common::ifEmpty($url_parsed['path'], '/');
                $path .= (!preg_match("@\?@si", $path)) ? '?url=' . rawurlencode($url) : '';
                try {
                    $socket = new SocketStream($host, $port, 30);
                    $socket->write("GET $path HTTP/1.0\r\nUser-Agent: artoo/1.0 (ArtOO PHP Framework)\r\nHost: $host\r\n\r\n");
                    $buffer = '';
                    while (!$socket->eof()) {
                        $buffer .= $socket->gets(4096);
                    }
                    $socket->close();
                    $buffer = explode("\r\n\r\n", $buffer, 2);
                    $response = $buffer[1];
                } catch (Exception $e) {
                    $try_curl = TRUE;
                }
            } else {
                $try_curl = TRUE;
            }
            if ($try_curl) {
                $ch = new Curly(
                    $url,
                    array(
                        'header'         => 0,
                        'timeout'        => 30,
                        'followlocation' => 1,
                        'useragent'      => 'artoo/1.0 (ArtOO PHP Framework)',
                        'returntransfer' => 1
                    )
                );
                $response = $ch->exec();
                $ch->close();
            }
        }
        return $response;
    }
}

class RssValidationException extends \Exception {
    
    public function __construct($error) {
        $error = $error ? $error : 'Feed validator returned an empty response';
        parent::__construct($error);
    }
}
?>