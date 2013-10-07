<?php
namespace Agilis;

Conf::check('S3_EXPIRE', 'S3_BUCKET', 'S3_KEYID', 'S3_SECRET_KEY');

class S3Url {
    
    const URL = 'http://s3.amazonaws.com';
    
    private $url;     
    
    public function __construct($object, $expires=S3_EXPIRE, $bucket=S3_BUCKET, $key_id=S3_KEYID, $secret_key=S3_SECRET_KEY) {
        $string_to_sign = "GET\n\n\n$expires\n/$bucket/$object";
        $signature  = urlencode($this->hex2b64(hash_hmac('sha1', $string_to_sign, $secret_key)));
        $this->url = self::URL . "/$bucket/$object?AWSAccessKeyId=$key_id&Expires=$expires&Signature=$signature";
    }
    
    public function __toString() { return $this->url; }
    
    private function hex2b64($str) {
        $raw = '';
        for ($i=0; $i < strlen($str); $i+=2) {
            $raw .= chr(hexdec(substr($str, $i, 2)));
        }
        return base64_encode($raw);
    }
}
?>