<?php
/**
 * 
 *    Copyright (C) 2002-2022 MlgmXyysd All Rights Reserved.
 *    Copyright (C) 2013-2022 MeowCat Studio All Rights Reserved.
 *    Copyright (C) 2020-2022 Meow Mobile All Rights Reserved.
 * 
 */

/**
 * PHP Android Debug Bridge Library
 * 
 * https://github.com/MlgmXyysd/php-adb
 * 
 * Simple wrapper of Android Debug Bridge for PHP.
 * 
 * @author MlgmXyysd
 * @version 1.0
 * 
 * All copyright in the software is not allowed to be deleted
 * or changed without permission.
 */

declare(strict_types=1);

namespace MeowMobile;

class ADB {

    const BIN_LINUX = "adb";
    const BIN_DARWIN = "adb-darwin";
    const BIN_WINDOWS = "adb.exe";

    public const CONNECT_TYPE_DEVICE = "device";
    public const CONNECT_TYPE_UNAUTHORIZED = "unauthorized";
    public const CONNECT_TYPE_OFFLINE = "offline";
    public const CONNECT_TYPE_SIDELOAD = "sideload";
    public const CONNECT_TYPE_RECOVERY = "recovery";

    private $bin;
    private $devices;

    function __construct() {
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
                $temp = array();
                switch ($device[1]) {
                    case "device":
                    case "recovery":
                        $transport = str_replace("transport_id:", "", $device[5]);
                        $temp["manufacturer"] = self::runAdb("-t " . $transport . " shell getprop ro.product.manufacturer")[0][0];
                        $temp["brand"] = self::runAdb("-t " . $transport . " shell getprop ro.product.brand")[0][0];
                        $temp["board"] = self::runAdb("-t " . $transport . " shell getprop ro.product.board")[0][0];
                        $temp["name"] = self::runAdb("-t " . $transport . " shell getprop ro.product.name")[0][0];
                    case "sideload":
                        $temp["serial"] = $device[0];
                        $temp["status"] = $device[1];
                        $temp["product"] = str_replace("product:", "", $device[2]);
                        $temp["model"] = str_replace("model:", "", $device[3]);
                        $temp["device"] = str_replace("device:", "", $device[4]);
                        $temp["transport"] = str_replace("transport_id:", "", $device[5]);
                        break;
                    case "unauthorized":
                    case "offline":
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

    public function setScreenSize($size = "reset", $device = "", $transport = false) {
        return self::runAdbJudge(self::getDeviceId($device, $transport) . "shell wm size " . $size));
    }

    public function getScreenSize($device = "", $transport = false) {
        $output = self::runAdb(self::getDeviceId($device, $transport) . "shell wm size");
        return self::judgeOutput($output) ? array(str_replace("Physical size: ", "", $output[0][0]), isset($output[0][1]) ? str_replace("Override size: ", "", $output[0][1]) : $physical) : false;
    }

    public function setScreenDensity($size = "reset", $device = "", $transport = false) {
        return self::runAdbJudge(self::getDeviceId($device, $transport) . "shell wm density " . $size);
    }

    public function getScreenDensity($device = "", $transport = false) {
        $output = self::runAdb(self::getDeviceId($device, $transport) . "shell wm density");
        return self::judgeOutput($output) ? array(str_replace("Physical density: ", "", $output[0][0]), isset($output[0][1]) ? str_replace("Override density: ", "", $output[0][1]) : $physical) : false;
    }

    public function getScreenshotPNG($device = "", $transport = false) {
        $output = self::runAdb(self::getDeviceId($device, $transport) . "exec-out screencap -p", true);
        return self::judgeOutput($output) ? $output[0] : false;
    }

    public function getPackage($package, $device = "", $transport = false) {
        $output = self::runAdb(self::getDeviceId($device, $transport) . "shell pm path " . $package);
        return self::judgeOutput($output) ? substr($output[0][0], 8) : false;
    }

    public function getCurrentActivity($device = "", $transport = false) {
        $output = self::runAdb(self::getDeviceId($device, $transport) . "shell \"dumpsys window | grep mCurrentFocus\"");
        if (!self::judgeOutput($output)) {
            return false;
        }
        if (str_contains($output[0][0], "mCurrentFocus=Window")) {
            $output = explode("/", trim(explode(" ", trim($output[0][0]))[2], "}"));
            return array($output[0], isset($output[1]) ? $output[1] : false);
        } else {
            return array(false, false);
        }
    }

    public function openDocumentUI($path = "", $device = "", $transport = false) {
        return self::runAdbJudge(self::getDeviceId($device, $transport) . "shell am start -a android.intent.action.VIEW -c android.intent.category.DEFAULT -t vnd.android.document/" . ($path === "" ? "root" : "directory -d content://com.android.externalstorage.documents/tree/primary:" . $path . "/document/primary:" . $path . "") . " com.android.documentsui/.files.FilesActivity");
    }

    public function runAdb($command, $raw = false) {
        return self::execShell($this -> bin . " "  . $command, $raw);
    }

    public function runAdbJudge($command) {
        return self::judgeOutput(self::runAdb($command));
    }

    private function judgeOutput($output, $target = 0) {
        return isset($output[1]) && $output[1] === $target ? true : false;
    }

    private function getDeviceId($device = "", $transport = false) {
        return $device === "" ? "" : ($transport ? "-t " : "-s ") . $device . " ";
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