<?php
// MoneyTransfer (Grid Presence)  V 0.5  07.07.13
// MoneyTransfer PW ObjectID FromID ToID Amount Type
// Copyright by Snoopy Pfeffer, 2013

include('config.php');

// initialize variables

$pw = $_GET["PW"];
$objectID = $_GET["ObjectID"];
$fromID = $_GET["FromID"];
$toID = $_GET["ToID"];
$amount = $_GET["Amount"];
$type = $_GET["Type"];

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

$dbUsers = mysql_connect($dbUsersHost, $dbUsersUser, $dbUsersPassword);

if (!$dbUsers) {
    die('Could not connect to database: ' . mysql_error());
}

mysql_select_db($dbName, $db);

// check if sender exists

$sql = "SELECT count(*) FROM Balances WHERE UserID=\"" . $fromID . "\"";

$result = mysql_query($sql, $db);

if (!$result) {
    die('Could not execute database query: ' . mysql_error());
}

$row = mysql_fetch_row($result);

if ($row[0] != 1) {
    die('Sender does not exit');
}

// check if receiver exists

$sql = "SELECT count(*) FROM Balances WHERE UserID=\"" . $toID . "\"";

$result = mysql_query($sql, $db);

if (!$result) {
    die('Could not execute database query: ' . mysql_error());
}

$row = mysql_fetch_row($result);

if ($row[0] == 0) {
    // automatically create new balance records

    $sql = "INSERT INTO Balances (UserID,Balance) VALUES (\"" . $agentID . "\"," . $startingBalance . ")";
    $result = mysql_query($sql, $db);

    if (!$result) {
        die('Could not execute database query: ' . mysql_error());
    }
}

// do money transfer

$sql = "BEGIN";

$result = mysql_query($sql, $db);

if (!$result) {
    die('Could not execute database query: ' . mysql_error());
}

$sql = "UPDATE Balances SET Balance = Balance - " . $amount . " WHERE UserID=\"" . $fromID . "\"";

$result = mysql_query($sql, $db);

if (!$result) {
    mysql_query("ROLLBACK", $db);
    die('Could not execute database query: ' . mysql_error());
}

$sql = "UPDATE Balances SET Balance = Balance + " . $amount . " WHERE UserID=\"" . $toID . "\"";

$result = mysql_query($sql, $db);

if (!$result) {
    mysql_query("ROLLBACK", $db);
    die('Could not execute database query: ' . mysql_error());
}

$sql = "INSERT INTO Transactions (ObjectID, FromID, ToID, Amount, Time, Type) VALUES (\"". $objectID . "\", \"" . $fromID . "\", \"" . $toID . "\", " . $amount . ", " . time() . ", " . $type . ")";

$result = mysql_query($sql, $db);

if (!$result) {
    mysql_query("ROLLBACK", $db);
    die('Could not execute database query: ' . mysql_error());
}

$sql = "COMMIT";

$result = mysql_query($sql, $db);

if (!$result) {
    die('Could not execute database query: ' . mysql_error());
}

// sender and receiver the same?

if ($fromID == $toID) {
    // do not send change notifications
    return;
}

// send change notification to sender

mysql_select_db($dbUsersName, $dbUsers);

$sqlUsers = "SELECT serverURI FROM Presence p, regions r WHERE p.UserID=\"" . $fromID . "\" AND p.RegionID=r.uuid";
$result = mysql_query($sqlUsers, $dbUsers);

if ($result && ($row = mysql_fetch_assoc($result))) {
    $uri = $row[serverURI];
    balance_changed_request($uri, $fromID);
}

// send change notification receiver

$sqlUsers = "SELECT serverURI FROM Presence p, regions r WHERE p.UserID=\"" . $toID . "\" AND p.RegionID=r.uuid";
$result = mysql_query($sqlUsers, $dbUsers);

if ($result && ($row = mysql_fetch_assoc($result))) {
    $uri = $row[serverURI];
    balance_changed_request($uri, $toID);
}

function  balance_changed_request($uri, $agentID)
{
  $url = $uri . "balance_changed/" . $agentID;
  $params = array('http' => array(
              'method' => 'POST',
              'content' => ''
            ));
  $ctx = stream_context_create($params);
  $fp = @fopen($url, 'rb', false, $ctx);
  if (!$fp) {
    return false;
  }
  $response = @stream_get_contents($fp);
  if ($response === false) {
    return false;
  }
  return true;
}
?>
