<?php
// ModifyBalance  V 0.4  22.10.12
// ModifyBalance PW AgentID Amount
// Amount to add (positive) or substract (negative) from user's balance.
// Copyright by Snoopy Pfeffer, 2012

include('config.php');

// initialize variables

$pw = $_GET["PW"];
$agentID = $_GET["AgentID"];
$amount = $_GET["Amount"];

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

// check if receiver exists

$sql = "SELECT count(*) FROM Balances WHERE UserID=\"" . $agentID . "\"";

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

// do modification

$sql = "BEGIN";

$result = mysql_query($sql, $db);

if (!$result) {
    die('Could not execute database query: ' . mysql_error());
}

$sql = "UPDATE Balances SET Balance = Balance + " . $amount . " WHERE UserID=\"" . $agentID . "\"";

$result = mysql_query($sql, $db);

if (!$result) {
    mysql_query("ROLLBACK", $db);
    die('Could not execute database query: ' . mysql_error());
}

$sql = "INSERT INTO Transactions (ObjectID, FromID, ToID, Amount, Time, Type) VALUES (\"00000000-0000-0000-0000-000000000000\", \"00000000-0000-0000-0000-000000000000\", \"" . $agentID . "\", " . $amount . ", " . time() . ", 9000)";

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

// send change notification

$sql = "SELECT ServerURI from Regions WHERE UserID=\"" . $agentID . "\"";
$result = mysql_query($sql, $db);

if ($result && ($row = mysql_fetch_assoc($result))) {
    $uri = $row[ServerURI];
    if (!balance_changed_request($uri, $agentID)) {
        $sql = "DELETE FROM Regions WHERE UserID=\"" . $agentID . "\" AND ServerURI=\"" . $uri . "\"";
        mysql_query($sql, $db);
    }
}

function  balance_changed_request($uri, $userID)
{
  $url = "http://" . $uri . "/balance_changed/" . $userID;
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
