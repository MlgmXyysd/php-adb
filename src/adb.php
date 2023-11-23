<?php
/**
 * 
 *    Copyright (C) 2002-2022 MlgmXyysd All Rights Reserved.
 *    Copyright (C) 2013-2022 MeowCat Studio All Rights Reserved.
 *    Copyright (C) 2020-2022 Meow Mobile All Rights Reserved.
 * 
 */

/**
 * 
 * PHP Android Debug Bridge Library
 * 
 * https://github.com/MlgmXyysd/php-adb
 * 
 * Simple wrapper of Android Debug Bridge for PHP. 
 * 
 * Environment requirement:
 *   - PHP
 * 
 * @author MlgmXyysd
 * @version 1.2
 * 
 * All copyright in the software is not allowed to be deleted
 * or changed without permission.
 * 
 */

declare(strict_types=1);

namespace MeowMobile;

/**
 * MeowMobile/ADB
 */
class ADB {
    
    const BIN_LINUX = "adb";
    const BIN_DARWIN = "adb-darwin";
    const BIN_WINDOWS = "adb.exe";

    const CONNECT_TYPE_DEVICE = "device";
    const CONNECT_TYPE_RECOVERY = "recovery";
    const CONNECT_TYPE_SIDELOAD = "sideload";
    const CONNECT_TYPE_RESCUE = "rescue";
    const CONNECT_TYPE_UNAUTHORIZED = "unauthorized";
    const CONNECT_TYPE_OFFLINE = "offline";

    public $bin;
    private $devices;

    function __construct($bin_path = "") {
        switch (PHP_OS_FAMILY) {
            case "Windows":
                $this -> bin = self::BIN_WINDOWS;
                break;
            case "Darwin":
                $this -> bin = self::BIN_DARWIN;
                break;
            default:
                $this -> bin = self::BIN_LINUX;
        }
		
		if ($bin_path !== "") {
			$sep = PHP_OS_FAMILY === "Windows" ? "\\" : "/";
			if (substr($bin_path, -1) !== $sep) {
				$bin_path .= DIRECTORY_SEPARATOR;
            }
            $this -> bin = "\"" . $bin_path . $this -> bin . "\"";
		}

        self::runAdb("root");

        self::refreshDeviceList();
    }

    /**
     * Refresh device list and get it
     * 
     * @access public
     * @return array Device list
     */
    public function refreshDeviceList() {
        $this -> devices = array();
        $result = self::runAdb("devices -l");
        if (self::judgeOutput($result)) {
            array_shift($result[0]); // List of devices attached
            foreach ($result[0] as $key => $value) {
                $value = preg_replace("/[ \t]+/is", " ", $value);
                $device = explode(" ", $value);
                $temp = array("serial" => "", "status" => "", "transport" => "");
                switch ($device[1]) {
                    case self::CONNECT_TYPE_DEVICE:
                    case self::CONNECT_TYPE_RECOVERY:
                        $transport = str_replace("transport_id:", "", $device[5]);
                        $temp["manufacturer"] = self::runAdb("-t " . $transport . " shell getprop ro.product.manufacturer")[0][0];
                        $temp["brand"] = self::runAdb("-t " . $transport . " shell getprop ro.product.brand")[0][0];
                        $temp["board"] = self::runAdb("-t " . $transport . " shell getprop ro.product.board")[0][0];
                        $temp["name"] = self::runAdb("-t " . $transport . " shell getprop ro.product.name")[0][0];
                    case self::CONNECT_TYPE_SIDELOAD:
                    case self::CONNECT_TYPE_RESCUE:
                        $temp["serial"] = $device[0];
                        $temp["status"] = $device[1];
                        $temp["product"] = str_replace("product:", "", $device[2]);
                        $temp["model"] = str_replace("model:", "", $device[3]);
                        $temp["device"] = str_replace("device:", "", $device[4]);
                        $temp["transport"] = str_replace("transport_id:", "", $device[5]);
                        break;
                    case self::CONNECT_TYPE_UNAUTHORIZED:
                    case self::CONNECT_TYPE_OFFLINE:
                        $temp["serial"] = $device[0];
                        $temp["status"] = $device[1];
                        $temp["transport"] = str_replace("transport_id:", "", $device[2]);
                        break;
                }
                $this -> devices[] = $temp;
            }
        }
        return $this -> devices;
    }

    /**
     * Get device list
     * 
     * @access public
     * @return array Device list
     */
    public function getDeviceList() {
        return $this -> devices;
    }

    /* TODO: Too lazy to write documents lmao */

    public function startServer() {
        return self::runAdbJudge("start-server");
    }

    public function killServer($force = false) {
        if ($force) {
            if (PHP_OS_FAMILY !== "Windows") {
                echo("Force termination is not implemented on non-Windows systems, fallbacking to normal." . PHP_EOL);
            } else {
                return self::judgeOutput(self::execShell("taskkill /f /im " . $this -> bin));
            }
        }
        return self::runAdbJudge("kill-server");
    }

    public function restartServer($force = false) {
        self::killServer($force);
        return startServer();
    }

    public function sendInput($type = "", $args = "", $device = "") {
        return self::runAdb($device . "shell input " . $type . " " . $args);
    }

    public function setScreenSize($size = "reset", $device = "") {
        return self::runAdbJudge($device . "shell wm size " . $size);
    }

    public function getScreenSize($device = "") {
        $o = self::runAdb($device . "shell wm size");
        return self::judgeOutput($o) ? array(str_replace("Physical size: ", "", $o[0][0]), isset($output[0][1]) ? str_replace("Override size: ", "", $o[0][1]) : $physical) : false;
    }

    public function setScreenDensity($size = "reset", $device = "") {
        return self::runAdbJudge($device . "shell wm density " . $size);
    }

    public function getScreenDensity($device = "") {
        $o = self::runAdb($device . "shell wm density");
        return self::judgeOutput($o) ? array(str_replace("Physical density: ", "", $o[0][0]), isset($o[0][1]) ? str_replace("Override density: ", "", $o[0][1]) : $physical) : false;
    }

    public function getScreenshotPNG($device = "") {
        $o = self::runAdb($device . "exec-out screencap -p", true);
        return self::judgeOutput($o) ? $o[0] : false;
    }

    public function getPackage($package, $device = "") {
        $o = self::runAdb($device . "shell pm path " . $package);
        return self::judgeOutput($o) ? substr($o[0][0], 8) : false;
    }

    public function getCurrentActivity($device = "") {
        $o = self::runAdb($device . "shell \"dumpsys window | grep mCurrentFocus\"");
        if (!self::judgeOutput($o)) {
            return array(false, false);
        }
        if (str_contains($o[0][0], "mCurrentFocus=Window")) {
            if (preg_match("/Window\{(.*)\}/", $o[0][0], $matches)) {
				$matches = explode(" ", $matches[1]);
				$o = explode("/", $matches[count($matches) - 1]);
				return array($o[0], isset($o[1]) ? $o[1] : false);
			}
        }
        return array(false, false);
    }

    public function getScreenState($device = "") {
        $o = self::runAdb($device . "shell \"dumpsys window policy | grep screenState\"");
        if (!self::judgeOutput($o)) {
            return false;
        }
        return str_contains($o[0][0], "SCREEN_STATE_ON");
    }

    public function openDocumentUI($path = "", $device = "") {
        // Content workaround from https://mlgmxyysd.meowcat.org/2021/02/18/android-r-saf-data/
        return self::runAdbJudge($device . "shell am start -a android.intent.action.VIEW -c android.intent.category.DEFAULT -t vnd.android.document/" . ($path === "" ? "root" : "directory -d content://com.android.externalstorage.documents/tree/primary:" . $path . "/document/primary:" . $path . "") . " com.android.documentsui/.files.FilesActivity");
    }

    public function clearLogcat($device = "") {
        return self::runAdb($device . "logcat -c");
    }

    public function runAdb($command, $raw = false) {
        return self::execShell($this -> bin . " "  . $command, $raw);
    }

    public function runAdbJudge($command) {
        return self::judgeOutput(self::runAdb($command));
    }

    /* Utilities */

    public function getDeviceId($device = "", $transport = false) {
        return $device === "" ? "" : ($transport ? "-t " : "-s ") . $device . " ";
    }

    public function judgeOutput($output, $target = 0) {
        return isset($output[1]) && $output[1] === $target ? true : false;
    }

    private function execShell($command, $raw = false) {
        ob_start();
        passthru($command . " 2>&1", $errorlevel);
        $output = ob_get_contents();
        ob_end_clean();
        return array($raw ? $output : explode(PHP_EOL, rtrim($output)), $errorlevel);
    }
}
?>
