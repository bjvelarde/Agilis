<?php
namespace Agilis;

class CvsBundler {

    public static function execute($action, $lib, $info, $renew_lock=FALSE) {
        $path = isset($info['path']) ? APP_ROOT . $info['path'] : VENDOR_PATH;
        $path .= substr($path, -1) == '/' ?  '' : '/';
        $dir = $path . $lib;
        $newlib = !is_dir($dir);
        $command = '';
        if ($action == 'install') {
            if ($newlib) {
                $command = "cvs co -d $lib {$info['source']}"; 'cvs co ' . $info['source'] . ' ' . $dir;
            } else {
                if ($renew_lock) {
                    if (!chdir($dir)) {
                        echo "FAILED to change dir to $dir, skipping $lib\n";
                    } else {
                        $command = 'cvs update';
                    }
                }
            }
        } else {
            if (!chdir($dir)) {
                echo "FAILED to change dir to $dir, skipping $lib\n";
            } else {
                $command = 'cvs update';
            }
        }
        if ($command) {
            $content_grabbed = Bundler::passthru($command);
            echo "$lib : $content_grabbed\n";
        } else {
            echo "$lib : nothing to do...\n";
        }
        return $info;
    }
}
?>