<?php
namespace Agilis;

use \Exception;

class Bundler {

    protected $bundles;
    protected $action;
    protected $renew_lock;
    protected $module;

    public function __construct($action='install', $module='') {
        $this->action = $action === 'install' ? 'install' : 'update';
        if (!file_exists(BUNDLE_FILE)) {
            throw new BundlerException("Can't find bundle file: " . BUNDLE_FILE);
        }
        if (!is_dir(VENDOR_PATH) && !mkdir(VENDOR_PATH)) {
            throw new BundlerException('Failed to find or create vendor dir');
        }
        if (!chdir(VENDOR_PATH)) {
            throw new BundlerException('Failed to change dir to vendor');
        }
        $this->renew_lock = (!file_exists(LOCK_FILE) || file_exists(LOCK_FILE) && filemtime(BUNDLE_FILE) > filemtime(LOCK_FILE));
        $bundles = json_decode(file_get_contents(BUNDLE_FILE), TRUE);
        if ($this->renew_lock) {
            if (file_exists(LOCK_FILE)) {
                $locked_bundles = json_decode(file_get_contents(LOCK_FILE), TRUE);
                foreach ($bundles as $k => $v) {
                    if (isset($locked_bundles[$k]) && !isset($bundles[$k])) {
                        unset($locked_bundles[$k]);
                    }
                }
                $bundles = $locked_bundles;
            }    
        } else {
            $bundles = file_exists(LOCK_FILE) ?
                       array_merge($bundles, json_decode(file_get_contents(LOCK_FILE), TRUE)) :
                       $bundles;
        }
        $this->bundles = $bundles;
        $this->module = $module;
    }

    public static function passthru($command) {
        ob_start();
        passthru($command);
        $str = ob_get_contents();
        ob_end_clean();
        return $str;
    }

    protected static function wipeCleanDir($path) {
        if (is_file($path)) {
            chmod($path, 0777);
            unlink($path);
        } elseif (is_dir($path)) {
            chmod($path, 0777);
            array_map(array(__CLASS__, 'wipeCleanDir'), glob($path . '/*')) == rmdir($path);
        }
    }

    protected function cleanUp() {
        if ($dh = opendir(VENDOR_PATH)) {
            while (($entry = readdir($dh)) !== false) {
                if ($entry != '.' && $entry != '..' && !isset($this->bundles[$entry])) {
                    if (is_dir(VENDOR_PATH . $entry . '/.git')) {
                        self::wipeCleanDir(VENDOR_PATH . $entry . '/.git');
                    }
                    if (is_dir(VENDOR_PATH . $entry . '/.svn')) {
                        self::wipeCleanDir(VENDOR_PATH . $entry . '/.svn');
                    }
                    if (is_dir(VENDOR_PATH . $entry . '/.cvs')) {
                        self::wipeCleanDir(VENDOR_PATH . $entry . '/.cvs');
                    }
                    self::wipeCleanDir(VENDOR_PATH . $entry);
                    echo "$entry</b> : removed\n";
                }
            }
        }
    }

    public function execute() {
        $lock = array();
        if ($this->module) {
            $info = $this->bundles[$this->module];
            $dvcs = isset($info['dvcs']) ? $info['dvcs'] : 'git';
            if (file_exists(LOCK_FILE)) {
                $lock = json_decode(file_get_contents(LOCK_FILE), TRUE);
            }
            $cwd = getcwd();
            if ($cwd != VENDOR_PATH) {
                if (!chdir(VENDOR_PATH)) {
                    die("Failed to change dir from $cwd to " . VENDOR_PATH);
                }
            }
            $bundler_class = ucfirst($dvcs) . 'Bundler';            
            $lock[$this->module] = $bundler_class::execute($this->action, $this->module, $info, $this->renew_lock);            
        } else {            
            foreach ($this->bundles as $lib => $info) {               
                $dvcs = isset($info['dvcs']) ? $info['dvcs'] : 'git';
                $cwd = getcwd();
                if ($cwd != VENDOR_PATH) {
                    if (!chdir(VENDOR_PATH)) {
                        die("Failed to change dir from $cwd to " . VENDOR_PATH);
                    }
                }
                $bundler_class = __NAMESPACE__ . "\\" . ucfirst($dvcs) . 'Bundler';            
                $lock[$lib] = $bundler_class::execute($this->action, $lib, $info, $this->renew_lock);
            }
            //$this->cleanUp();
        }
        if ($lock) {
            file_put_contents(LOCK_FILE, json_encode($lock));
        }        
    }

}



class BundlerException extends Exception {}
?>