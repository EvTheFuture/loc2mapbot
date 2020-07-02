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
define("CONFIG_FILE", "../config.json");

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

date_default_timezone_set('UTC'); 

$id = $_GET["id"];
$pw = $_GET["token"];

$result = array();
$calc = base64_encode(md5($config["SECRET_SALT"].$id.$config["SECRET_SALT"]));

if ($pw != $calc) {
    $result["status"] = "Invalid Token";
} else {
    require_once("../Db.php");

    checkField("DB", $config);
    checkField("username", $config["DB"]);
    checkField("password", $config["DB"]);
    checkField("dbname", $config["DB"]);
    checkField("host", $config["DB"]);

    $db = new Db($config["DB"]);    

    $id = $db->quote($id);
    $rows = $db->select("SELECT * from locations where gid=$id");

    $dir = "/images/";
    $result["status"] = "OK";
    $result["time"] = time();
    foreach($rows as $row) {
	$row["image_url"] = "https://".$_SERVER["SERVER_NAME"].$dir.$row["image"].".jpg";
	if (file_exists(".$dir".$row["image"]."-icon.png"))
	    $row["icon_url"] = "https://".$_SERVER["SERVER_NAME"].$dir.$row["image"]."-icon.png";
	else
	    $row["icon_url"] = "https://".$_SERVER["SERVER_NAME"]."/unknown-icon.png";

	$result["rows"][] = $row;
    }
}
header("Content-Type: application/json");
echo json_encode($result, JSON_PRETTY_PRINT);

?>
