<?php
/**
 * @package Agilis
 * @version 1.0
 * @author Benjie Velarde
 * @copyright (c) 2013, Benjie Velarde bvelarde@gmail.com
 * @license http://opensource.org/licenses/PHP-3.0
 */
namespace Agilis;

abstract class Migration { 

    const DIR = 'db/migration/';

    public static function generate($name, $gen_model=TRUE) {
        $name  = (String::is_singular($name)) ? String::pluralize($name)->to_s : $name;
        $file  = APP_ROOT . self::DIR . date('YmdHis') . '-' . String::camelize($name)->underscore() . '.php';
        $class = String::camelize($name);
        $contents = "<?php\nuse Agilis\\Migration;\nuse Agilis\\Table;\nuse Agilis\\Translator;\n\nclass $class extends Migration {\n\n    public function up() {\n    }\n\n    public function down() {\n    }\n}\n?>";        
        if (file_put_contents($file, $contents)) {
            if (substr($name, 0, 7) == 'create_' && $gen_model) {
                $model = String::substr($name, 7)->singularize()->to_s;
                Model::generate($model);
            }
            return $file;
        }
        return FALSE;
    }
    
    abstract public function up();
    
    abstract public function down();
}
?>

