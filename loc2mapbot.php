#!/usr/bin/php
<?php
# This file is part of loc2mapbot (https://github.com/mukens/loc2mapbot)
# Copyright (c) 2020 Magnus Sandin
# 
# This program is free software: you can redistribute it and/or modify  
# it under the terms of the GNU General Public License as published by  
# the Free Software Foundation, version 3.
#
# This program is distributed in the hope that it will be useful, but 
# WITHOUT ANY WARRANTY; without even the implied warranty of 
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU 
# General Public License for more details.
#
# You should have received a copy of the GNU General Public License 
# along with this program. If not, see <http://www.gnu.org/licenses/>.
#

declare(ticks = 1);

date_default_timezone_set('UTC'); 

define("CONFIG_FILE", "config.json");
define("DATA_FILE", ".data.json");

require_once "vendor/autoload.php";
require_once "Db.php";

function clean_up($signo = false) {
    global $data;

    echo "\nWriting out data...\n";
    echo "ID: ".$data["last_update_id"]."\n";

    # Store dynamic data for next run
    file_put_contents(DATA_FILE, json_encode($data, JSON_PRETTY_PRINT));

    exit(0);
}

#-+---------------------------------------------------------------
#-+-
#-+---------------------------------------------------------------
try {
    $data = json_decode(@file_get_contents(DATA_FILE), true);
} catch (Exception $e) {
    $data = array("last_update_id" => 0);
}

try {
    $config = json_decode(@file_get_contents(CONFIG_FILE), true);
} catch (Exception $e) {
    die("Unable to read config file: ".COFIG_FILE);
}

function checkField($tag, $config) {
    if (!@$config[$tag])
	die("$tag is missing from config file...\n");
}

checkField("SECRET_SALT", $config);
checkField("API_TOKEN", $config);
checkField("LOG_FILE", $config);
checkField("DB", $config);
checkField("username", $config["DB"]);
checkField("password", $config["DB"]);
checkField("dbname", $config["DB"]);
checkField("host", $config["DB"]);

$db = new Db($config["DB"]);    

function debug($msg, $message=null) {
    global $config;

    $date = new DateTime();
    $date->setTimezone(new DateTimeZone('Europe/Stockholm'));
    $time = $date->format('Y-m-d H:i:s');

    $res = "";
    if ($message != null) {
        $from = $message->getFrom();
        $res .= "$time From: ".$from->getFirstName()." ".$from->getLastName()." @".$from->getUsername()." (".$from->getId().")";
        if ($from->getId() == $message->getChat()->getId())
            $res .= "\n";
        else
            $res .= ", Chat: ".$message->getChat()->getTitle()." (".$message->getChat()->getId().")\n";
    }

    $res .= "$time $msg\n";
    echo $res;
    file_put_contents($config["LOG_FILE"], $res, FILE_APPEND);
}

function postPad($msg, $size=10, $pad=" ") {
    for ($i = strlen($msg); $i <= $size; $i++)
        $msg .= $pad;

    return $msg;
}

function saveData() {
    global $data;

    file_put_contents(DATA_FILE, json_encode($data, JSON_PRETTY_PRINT));
}

function md5Data($in) {
    return md5(json_encode($in));
}

function sendImage($bot, $chatId, $file) {
    if (file_exists("$file")) {
        debug("Sending file '$file' (".basename($file).")");
        $cfile = new CURLFile("$file", "image/png", basename($file));
        try {
            $bot->sendPhoto($chatId, $cfile);
            $error = "";
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    } else
        $error = "Unable to find image...";

    return $error;
}

function handleGetId($bot, $message) {
    global $config;

    $bot->sendChatAction($message->getChat()->getId(), "typing");

    $id = $message->getChat()->getId();
    $pw = base64_encode(md5($config["SECRET_SALT"]."$id".$config["SECRET_SALT"]));

    $bot->sendMessage($message->getChat()->getId(), "ID: $id\nToken: $pw");

    debug("Sent token to chat", $message);
}

function handleLocation($bot, $message) {
    global $db, $config;

    $allphotos = $bot->getUserProfilePhotos($message->getFrom()->getId())->getPhotos();
    if ($allphotos) {
        for ($i = 0; $i < count($allphotos); $i++) {
            $photos = $allphotos[$i];
            $photo = end($photos);
            $filename = $photo->getFileId();
            $destfile = "./www/images/${filename}.jpg";
            $iconfile = "./www/images/${filename}-icon.png";

            if (!file_exists($destfile)) {
                debug("Trying to get fileId: $filename for user: ".$message->getFrom()->getUsername());
                try {
                    $file = $bot->getFile($photo->getFileId());
                    $url = "https://api.telegram.org/file/bot".$config["API_TOKEN"]."/".$file->getFilePath();
                    shell_exec("wget -q -O '$destfile' '$url'");
                    shell_exec("convert '$destfile' -resize 72x72 -alpha on \( +clone -channel a -fx 0 \) +swap ./player-mask.png -composite ".
                               "-size 72x12 xc:none -append ./player-ring.png -composite '$iconfile'");
                    break;
                } catch (Exception $e) {
                    debug("Unable to download file... ");
                    $filename = "unknown";
                }
            } else {
                break;
            }
        }
    } else {
        $filename = "unknown";
    }

    $location = $message->getLocation();

    $firstname = $db->quote($message->getFrom()->getFirstName());
    $lastname = $db->quote($message->getFrom()->getLastName());
    $username = $db->quote($message->getFrom()->getUsername());
    $uid = $db->quote($message->getFrom()->getId());
    $gid = $db->quote($message->getChat()->getId());
    $lat = $db->quote($location->getLatitude());
    $lng = $db->quote($location->getLongitude());
    $image = $db->quote($filename);
    $time = time();

    $parms = "firstname=$firstname, lastname=$lastname, username=$username, lat=$lat, lng=$lng, time=$time, image=$image";
    $query = "INSERT INTO locations set uid=$uid, gid=$gid, $parms ON DUPLICATE KEY UPDATE $parms";
    $result = $db->query($query);

    $query = "DELETE FROM locations where time < ".($time - 1 * 24 * 3600);
    $result = $db->query($query);

    debug("Updated location for $username - time: $time");
}

function handleUpdate($bot, $update) {
    global $data;

    $message = $update->getMessage();

    if ($message == null)
        $message = $update->getEditedMessage();

    if ($message == null)
        return;

    $cmd = trim(strtolower($message->getText()));

    //debug("Line: ".$message->getText(), $message);

    if ($message->getLocation() != null) {
        handleLocation($bot, $message);
        return;
    }

    if (strpos($cmd, '/') !== 0)
        return;

    $cmd = str_replace("@loc2mapbot", "", strtolower($cmd));

    $index = strpos($cmd, " ");
    if ($index !== false) {
        $args = trim(substr($cmd, $index));
        $cmd = substr($cmd, 0, $index);
    } else
        $args = false;

//    if (in_array($message->getChat()->getId(), $data["acl"])) {
        switch ($cmd) {
            case "/token":
                handleGetId($bot, $message);
                break;

            default:
                break;
        }
//    } else
//        $bot->sendMessage($message->getChat()->getId(), "Access Denied...");
}

$failcount = 0;

debug("Bot started...");

while (true) {
    $updates = array();

    try {
        $bot = new \TelegramBot\Api\Client($config["API_TOKEN"]);
//        $bot->run();

        while (true) {
            $md5Data = md5Data($data);
            $updates = $bot->getUpdates($data["last_update_id"], 100, 60);
            if ($updates) {
		foreach ($updates as $update) {
		    handleUpdate($bot, $update);
		}

                $tmp = end($updates);
                $data["last_update_id"] = $tmp->getUpdateId() + 1;
            } else
                sleep(1);

            $newmd5Data = md5Data($data);
            if ($md5Data != $newmd5Data)
                saveData();
        }

    } catch (\TelegramBot\Api\Exception $e) {
        echo "ERROR: ".$e->getMessage()."\n";
        $tmp = end($updates);

        if (is_object($tmp) && $tmp->getUpdateId())
            $data["last_update_id"] = $tmp->getUpdateId() + 1;

        saveData();
    }

    if ($failcount > 2)
        sleep(10);
    else
        sleep(5);

    $failcount++;
    echo "Failed times: $failcount\n";
}

clean_up();

?>
