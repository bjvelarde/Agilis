<?php
/**
 * @author Benjie Velarde
 * @copyright (c) 2009, Benjie Velarde
 * @license http://opensource.org/licenses/bsd-license.php The BSD License
 */
namespace Agilis;

class UserAgent {

    private $os;
    private $browser;

    public function __construct() {
        $this->os = $this->browser = 'Unknown';
        if (preg_match('/^(([^\s]+)\/([^\s]+))[^\(]*\(([^\)]+)\)(\s(([^\/]+)\/[\d\.]+\s)?(.*))?$/', $_SERVER['HTTP_USER_AGENT'], $matches)) {
            $details = explode(';', $matches[4]);
            $method = strtolower(trim($matches[2]));
            if (method_exists($this, $method)) {
                $this->{$method}($matches, $details);
            }
        }
        if ($this->os == 'Unknown') {
            $this->os = PHP_OS;
        }
    }

    public function __get($var) {
        if ($var == 'os' || $var == 'browser') {
            return $this->{$var};
        }
        return NULL;
    }

    private function getOSVersion($os, $info='') {
        $os = trim($os);
        $this->os = $os;
        switch ($os) {
            case 'Windows NT 6.0':
                $this->os = 'Windows Vista';
                break;
            case 'Windows NT 5.2':
                $this->os = 'Windows Server 2003 or Windows XP x64';
                break;
            case 'Windows NT 5.1':
            case 'Win32':
                $this->os = 'Windows XP';
                break;
            case 'Windows NT 5.01':
                $this->os = 'Windows 2000 (SP1)';
                break;
            case 'Windows NT 5.0' :
                $this->os = 'Windows 2000';
                break;
            case 'Win95':
                $this->os = 'Windows 95';
                break;
            case 'Win16':
                $this->os = 'Windows 3.1x';
                break;
            case 'Linux i686':
                if ($info)  {
                    preg_match('/(' . implode('|', $this->getLinuxDistros()) . '(\/([\d\.]+))?)/i', $info, $distro);
                    $this->os .= ($distro)? ' (' . $distro[0] . ')': '';
                }
                break;
            case 'Mac_PowerPC':
            case 'PPC':
                $this->os = 'Mac PowerPC';
                break;
        }
    }

    private function opera(&$matches, &$details) {
        $this->browser = trim($matches[1]);
        $os = array_shift($details);
        if (trim($os) == 'X11') {
            $this->os = array_shift($details);
        } else {
            $this->getOSVersion($os);
        }
    }

    private function mozilla(&$matches, &$details) {
        $version = trim($matches[3]);
        if ($version == '5.0') {
            $method = 'mozilla50' . $matches[7];
            if (method_exists($this, $method)) {
                $os_code = $this->{$method}($details, $matches[8]);
                $this->getOSVersion($os_code, $matches[8]);
            }
        } else {
            $this->mozillaLower($version, $details);
            if ($this->os == 'Unknown' && strpos($this->browser, 'Windows')) {
                $this->os = 'Windows (Unknown version)';
            }
        }
    }

    private function mozilla50Gecko(&$details, $info) {
        $version  = array_pop($details);
        $langcode = array_pop($details);
        $os_code  = array_pop($details);
        preg_match_all('/[^\/\s]+\/[\d\.]+/', $info, $matches2, PREG_SET_ORDER);
        if ($matches2) {
            if (strpos($matches2[0][0], 'Epiphany') || count($matches2) == 1) {
                $this->browser = trim($matches2[0][0]);
                if (strpos($matches2[0][0], 'Firefox') && count($details) > 5) {
                    $this->browser = trim($details[2]);
                }
            } else {
                $this->browser = trim($matches2[1][0]);
            }
        } else {
            if (strpos($version, ':')) {
                $browser = explode(':', $version);
                $this->browser = 'Gecko/' . trim($browser[1]);
            } else {
                $this->browser = preg_match('/^[^\/\s]+\/[\d\.]+$/', $version)? $version: 'Unknown';
            }
        }
        return $os_code;
    }

    private function mozilla50AppleWebKit(&$details, $info) {
        $langcode = array_pop($details);
        $os_code  = array_pop($details);
        $browser = explode(' ', $info);
        $this->browser = array_pop($browser);
        return $os_code;
    }

    private function mozillaLower($version, &$details) {
        $this->browser = $details[1];
        $os = '';
        if (count($details) == 4 && $version == '4.0') {
            if (!strpos($details[3], '.NET')) {
                $this->browser = array_pop($details);
            }
        } elseif (count($details) > 4 && $version == '2.0') {
            $os = array_pop($details);
        }
        $this->browser = str_replace('MSIE', 'Internet Explorer', $this->browser);
        $this->browser = str_replace('MSPIE', 'PDA Portable IE', $this->browser);
        if (!$os) {
            $os = (strtolower(trim($details[0])) == 'compatible')? $details[2]: $details[0];
        }
        $this->getOSVersion($os);
        if (strpos($this->browser, 'RISC OS')) {
            $browser = $this->os;
            $this->os = $this->browser;
            $this->browser = $browser;
        }
    }

    private function getLinuxDistros() {
        return array('RedHat', 'Debian', 'Suse', 'Mandrake', 'Gentoo', 'Ubuntu');
    }
}
?>
