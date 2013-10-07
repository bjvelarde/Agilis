<?php
/**
 * @author Benjie Velarde
 * @copyright (c) 2013, Benjie Velarde
 * @license http://opensource.org/licenses/bsd-license.php The BSD License
 */
namespace Agilis;
/**
 * Image uploader class
 */ 
class ImageUpload extends FileUpload {
    /**
     * Constructor
     *
     * @param Params $params
     */
    public function __construct(Params $params) {
        $params->filters(array(
            'image/gif', 
            'image/jpeg', 
            'images/png', 
            'image/vnd.wap.wbmp'
        ));
        parent::__construct($params);
    }
}
?>