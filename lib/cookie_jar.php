<?php
namespace Agilis;

class CookieJar {

    private $jar;

    public function __construct($url, Params $data, $jar) {
        $curl = new Curly($url, Params::post(TRUE)->cookiejar($jar));
        $curl->postfields = $data->getElements();
        ob_start();      
        $curl_exec->(); 
        ob_end_clean(); 
        $this->jar = $jar;
    }
    
    public function read($url) {
        $curl = new Curly($url, Params::returntransfer(TRUE)->cookiefile($this->jar));
        return $curl->exec();
    }
}
?>