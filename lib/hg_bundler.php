<?php
namespace Agilis;

class HgBundler {

    protected static function getTags() {
        return Bundler::passthru('hg tags');
    }

    protected static function getVersion($ver) {
        $version = $ver;
        $verstr  = substr($ver, 0, 2);        
        if (in_array($verstr, array('~>', '>=', '<='))) {
            $version = 'master';
            $verinfo = explode('.', trim(substr($ver, 2)));
            $compare_floor_index = count($verinfo) - 1;
            if ($verstr == '~>') {
                $compare_ceiling_index = count($verinfo) - 2;
            }
            $tags = self::getTags();
            if ($tags) {               
                $tags = explode("\n", $tags);
                foreach ($tags as $tag) {
                    if ($tag) {
                        $tagver = explode('.', $tag);
                        $ceiling_ok = isset($compare_ceiling_index) ? ($tagver[$compare_ceiling_index] + 1) > $verinfo[$compare_ceiling_index] : TRUE;
                        if ($verstr == '~>' || $verstr == '>=') {
                            if ($tagver[$compare_floor_index] >= $verinfo[$compare_floor_index] && $ceiling_ok) {
                                $version = $tag;
                                break;
                            }
                        } elseif ($verstr == '<=') {
                            if ($tagver[$compare_floor_index] <= $verinfo[$compare_floor_index]) {
                                $version = $tag;
                                break;
                            }
                        }
                    }
                }
                $version = ($version == 'master') ? array_pop($tags) : $version;
                $version = ($version == '') ? array_pop($tags) : $version;
            }
        } else {
            $oper = substr($ver, 0, 1);
            if ($oper == '>' || $oper == '<') {
                $version = 'master';
                $verinfo = explode('.', trim(substr($ver, 1)));
                $compare_floor_index = count($verinfo) - 1;
                $compare_floor   = $verinfo[$compare_floor_index];
                $tags = self::getTags();
                if ($tags) {
                    $tags = explode("\n", $tags);
                    foreach ($tags as $tag) {
                        if ($tag) {
                            $tagver = explode('.', $tag);
                            if ($oper == '>' && $tagver[$compare_floor_index] > $verinfo[$compare_floor_index]) {
                                $version = $tag;
                                break;
                            } elseif ($oper == '<' && $tagver[$compare_floor_index] < $verinfo[$compare_floor_index]) {
                                $version = $tag;
                                break;
                            }
                        }
                    }
                    $version = ($version == 'master') ? array_pop($tags) : $version;
                    $version = ($version == '') ? array_pop($tags) : $version;
                }
            }
        }
        return $version;
    }
    
    public static function execute($action, $lib, $info, $renew_lock=FALSE) {
        $path = isset($info['path']) ? APP_ROOT . $info['path'] : VENDOR_PATH;
        $path .= substr($path, -1) == '/' ?  '' : '/';
        $dir = $path . $lib;
        $newlib = !is_dir($dir);
        $command = '';
        if ($action == 'install') {
            if ($newlib) {
                $command = 'hg clone ' . $info['source'] . ' ' . $dir;
                if (isset($info['version'])) {
                    Bundler::passthru($command);
                    if (!chdir($dir)) {
                        echo "FAILED to change dir to $dir, skipping $lib\n";
                    }
                    $version = self::getVersion($info['version']);
                    $command = "hg pull origin $version";
                    $info['version'] = $version;
                }
            } else {
                if ($renew_lock) {
                    if (!chdir($dir)) {
                        echo "FAILED to change dir to $dir, skipping $lib\n";
                    } else {
                        if (isset($info['version']) && $info['version'] != 'master') {
                            $version = self::getVersion($info['version']);
                            $info['version'] = $version;
                        }
                        $command = 'hg pull';
                        if (isset($version)) {
                            $command .= " origin $version";
                            Bundler::passthru("hg checkout $version");
                        } else {
                            Bundler::passthru('hg checkout master');
                        }
                    }
                }
            }
        } else {
            if (!chdir($dir)) {
                echo "FAILED to change dir to $dir, skipping $lib\n";
            } else {
                if (isset($info['version']) && $info['version'] != 'master') {
                    $version = self::getVersion($info['version']);
                    $info['version'] = $version;
                }
                $command = 'hg pull';
                if (isset($version)) {
                    $command .= " origin $version";
                    Bundler::passthru("hg checkout $version");
                } else {
                    Bundler::passthru('hg checkout master');
                }
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