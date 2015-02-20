<?php
// MoneyTransfer  V 0.4  22.10.12
// MoneyTransfer PW ObjectID FromID ToID Amount Type
// Copyright by Snoopy Pfeffer, 2012

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

$sql = "SELECT ServerURI from Regions where UserID=\"" . $fromID . "\"";
$result = mysql_query($sql, $db);

if ($result && ($row = mysql_fetch_assoc($result))) {
    $uri = $row[ServerURI];
    if (!balance_changed_request($uri, $fromID)) {
        $sql = "DELETE FROM Regions WHERE UserID=\"" . $fromID . "\" AND ServerURI=\"" . $uri . "\"";
        mysql_query($sql, $db);
    }
}

// send change notification receiver

$sql = "SELECT ServerURI from Regions where UserID=\"" . $toID . "\"";
$result = mysql_query($sql, $db);

if ($result && ($row = mysql_fetch_assoc($result))) {
    $uri = $row[ServerURI];
    if (!balance_changed_request($uri, $toID)) {
        $sql = "DELETE FROM Regions WHERE UserID=\"" . $toID . "\" AND ServerURI=\"" . $uri . "\"";
        mysql_query($sql, $db);
    }
}

function  balance_changed_request($uri, $agentID)
{
  $url = "http://" . $uri . "/balance_changed/" . $agentID;
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
