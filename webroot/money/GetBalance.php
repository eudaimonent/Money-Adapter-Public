<?php
// GetBalance  V 0.2  22.10.12
// GetBalance PW AgentID
// Copyright by Snoopy Pfeffer, 2012

include('config.php');

// initialize variables

$pw = $_GET["PW"];
$agentID = $_GET["AgentID"];

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

// get balance

$sql = "SELECT Balance FROM Balances WHERE UserID=\"" . $agentID . "\"";
$result = mysql_query($sql, $db);

if (!$result) {
    die('Could not execute database query: ' . mysql_error());
}

if ($row = mysql_fetch_assoc($result)) {
    echo $row[Balance];
} else {
    // automatically create new balance records

    $sql = "INSERT INTO Balances (UserID,Balance) VALUES (\"" . $agentID . "\"," . $startingBalance . ")";
    $result = mysql_query($sql, $db);

    if (!$result) {
        die('Could not execute database query: ' . mysql_error());
    }

    echo $startingBalance;
}
?>
