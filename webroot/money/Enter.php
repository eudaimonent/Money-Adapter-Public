<?php
// Enter  V 0.1  12.06.11
// Enter PW AgentID URI
// Copyright by Snoopy Pfeffer, 2011

include('config.php');

// initialize variables

$pw = $_GET["PW"];
$agentID = $_GET["AgentID"];
$uri = $_GET["URI"];

// check requester

if ($CHECK_HOST && $DEF_DB_PASSWORD[$_SERVER['REMOTE_ADDR']] == '') {
    echo "error: access not permitted";
    exit;
}

// check password

if ($pw != $moneyPassword) {
    echo "error: wrong password";
    exit;
}

// database connection

$db = mysql_connect($dbHost, $dbUser, $dbPassword);

if (!$db) {
    die('Could not connect to database: ' . mysql_error());
}

mysql_select_db($dbName, $db);

// add server URI

$sql = "INSERT INTO Regions (UserID, ServerURI) VALUES (\"" . $agentID . "\", \"" . $uri . "\")";
$result = mysql_query($sql, $db);

if (!$result) {
    die('Could not execute database query: ' . mysql_error());
}
?>
