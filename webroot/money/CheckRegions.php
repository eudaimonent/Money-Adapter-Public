<?php
// CheckRegions  V 0.3  07.10.12
// CheckRegions PW
// Check and fix region table entries.
// Copyright by Snoopy Pfeffer, 2012

include('config.php');

// initialize variables

$pw = $_GET["PW"];

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

// send change notification

$sql = "SELECT UserID,ServerURI from Regions";
$result = mysql_query($sql, $db);

while ($result && ($row = mysql_fetch_assoc($result))) {
    $agentID = $row[UserID];
    $uri = $row[ServerURI];
    if (!balance_changed_request($uri, $agentID)) {
        echo "Deleting " . $agentID . " from " . $uri . "<BR>";
        $sql = "DELETE FROM Regions where UserID=\"" . $agentID . "\" AND ServerURI=\"" . $uri . "\"";
        mysql_query($sql, $db);
    }
}

echo "Done.";

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
