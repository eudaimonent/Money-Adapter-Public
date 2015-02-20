<?php
// MoneyReport  V 0.3  07.09.12
// MoneyReport PW AgentID Time Email
// Create money transaction report for the last 31 days or optionally for the given time period.
// Show that report (string returned) or optionally send it to the given email address.
// Copyright by Snoopy Pfeffer, 2012

include('config.php');

// initialize variables

$pw = $_GET["PW"];
$agentID = $_GET["AgentID"];
$time = $_GET["Time"];
$email = $_GET["Email"];

if ($time == "") {
    $time = time() - 60 * 60 * 24 * 31; // last 31 days
}

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

// HTML header

$out = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">' . "\n";
$out .= '<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">' . "\n";
$out .= "<head><title>Money Report</title></head>\n<body>\n";

// get balance

mysql_select_db($dbName, $db);
$sql = "SELECT Balance FROM Balances WHERE UserID=\"" . $agentID . "\"";
$result = mysql_query($sql, $db);

if (!$result) {
    die('Could not execute database query: ' . mysql_error());
}

if ($row = mysql_fetch_assoc($result)) {
    $out .= "<p>Your current balance is " . $currency . " " . $row[Balance] . "</p>\n<p>&nbsp;</p>\n";
}

// iterate over money transactions

$out .= "<p>Money Transactions History:</p>\n<p>&nbsp;</p>\n";

mysql_select_db($dbName, $db);
$sql = "SELECT ObjectID, FromID, ToID, Amount, Time, Type FROM Transactions WHERE (FromID=\"" . $agentID . "\" OR ToID=\"" . $agentID . "\") AND Time>=" . $time . " ORDER BY Time ASC";
$result = mysql_query($sql, $db);

if (!$result) {
    die('Could not execute database query: ' . mysql_error());
}

$out .= '<table border="1" cellspacing="0" cellpadding="4">';
$out .= "\n<tr><th>Time (UTC)</th><th>Amount</th><th>Type</th><th>Details</th></tr>\n";

while ($row = mysql_fetch_assoc($result)) {
    $ts = $row[Time];
    $date = new DateTime("@$ts");
    $timeString = $date->format('Y-m-d H:i:s');

    $out .= "<tr><td>" . $timeString . "</td>";

    switch ($row[Type]) {
        case 1002: // Group Create
            if ($row[FromID] == $agentID) {
                $out .= "<td>" . $currency . " " . -$row[Amount] . "</td><td>Group_Create</td>";
                $out .= "<td>You have created a group.</td></tr>\n";
            } else {
                $out .= "<td>" . $currency . " " . $row[Amount] . "</td><td>Group_Create</td>";
                $out .= "<td>You received group creation charge from user " . username($row[FromID], $dbUsers, $dbUsersName) . "</td></tr>\n";
            }
            break;
        case 1101: // Upload Charge
            if ($row[FromID] == $agentID) {
                $out .= "<td>" . $currency . " " . -$row[Amount] . "</td><td>Upload_Charge</td>";
                $out .= "<td>You have uploaded assets.</td></tr>\n";
            } else {
                $out .= "<td>" . $currency . " " . $row[Amount] . "</td><td>Upload_Charge</td>";
                $out .= "<td>You received asset upload charge from user " . username($row[FromID], $dbUsers, $dbUsersName) . "</td></tr>\n";
            }
            break;
        case 5000: // ObjectSale
            if ($row[FromID] == $agentID) {
                if ($row[ToID] == $agentID) {
                    $out .=  "<td>" . $currency . " +/-" . $row[Amount] . "</td><td>Object_Sale</td>";
                    $out .=  "<td>You bought object " . $row[ObjectID] . " from yourself</td></tr>\n";
                } else {
                    $out .= "<td>" . $currency . " " . -$row[Amount] . "</td><td>Object_Sale</td>";
                    $out .= "<td>You bought object " . $row[ObjectID] . " from user " . username($row[ToID], $dbUsers, $dbUsersName) . "</td></tr>\n";
                }
            } else {
                if ($row[ToID] == $agentID) {
                    $out .= "<td>" . $currency . " " . $row[Amount] . "</td><td>Object_Sale</td>";
                    $out .= "<td>User " . username($row[FromID], $dbUsers, $dbUsersName) . " bought object " . $row[ObjectID] . " from you</td></tr>\n";
                }
            }
            break;
        case 5001: // Gift
            if ($row[FromID] == $agentID) {
                $out .= "<td>" . $currency . " " . -$row[Amount] . "</td><td>User_Pays_User</td>";
                $out .= "<td>You gave money to user " . username($row[ToID], $dbUsers, $dbUsersName) . "</td></tr>\n";
            } else {
                $out .= "<td>" . $currency . " " . $row[Amount] . "</td><td>User_Pays_User</td>";
                $out .= "<td>User " . username($row[FromID], $dbUsers, $dbUsersName) . " gave you money</td></tr>\n";
            }
            break;
        case 5008: // PayObject
            if ($row[FromID] == $agentID) {
                if ($row[ToID] == $agentID) {
                    $out .= "<td>" . $currency . " +/-" . $row[Amount] . "</td><td>User_Pays_Object</td>";
                    $out .= "<td>You gave money to object " . $row[ObjectID] . " owned by yourself</td></tr>\n";
                } else {
                    $out .= "<td>" . $currency . " " . -$row[Amount] . "</td><td>User_Pays_Object</td>";
                    $out .= "<td>You gave money to object " . $row[ObjectID] . " owned by user " . username($row[ToID], $dbUsers, $dbUsersName) . "</td></tr>\n";
                }
            } else {
                if ($row[ToID] == $agentID) {
                    $out .= "<td>" . $currency . " " . $row[Amount] . "</td><td>User_Pays_Object</td>";
                    $out .= "<td>User " . username($row[FromID], $dbUsers, $dbUsersName) . " gave money to object " . $row[ObjectID] . " owned by you</td></tr>\n";
                }
            }
            break;
        case 5009: // ObjectPays
            if ($row[FromID] == $agentID) {
                if ($row[ToID] == $agentID) {
                    $out .= "<td>" . $currency . " +/-" . $row[Amount] . "</td><td>Object_Pays_User</td>";
                    $out .= "<td>Object " . $row[ObjectID] . " owned by yourself gave money</td></tr>\n";
                } else {
                    $out .= "<td>" . $currency . " " . -$row[Amount] . "</td><td>Object_Pays_User</td>";
                    $out .= "<td>Object " . $row[ObjectID] . " owned by yourself gave money to user " . username($row[ToID], $dbUsers, $dbUsersName) . "</td></tr>\n";
                }
            } else {
                if ($row[ToID] == $agentID) {
                    $out .= "<td>" . $currency . " " . $row[Amount] . "</td><td>Object_Pays_User</td>";
                    $out .= "<td>Object " . $row[ObjectID] . " owned by user " . username($row[FromID], $dbUsers, $dbUsersName) . " gave you money</td></tr>\n";
                }
            }
            break;
        case 5013: // BuyLand
            if ($row[FromID] == $agentID) {
                $out .= "<td>" . $currency . " " . -$row[Amount] . "</td><td>Buy_Land</td>";
                $out .= "<td>You bought land from user " . username($row[ToID], $dbUsers, $dbUsersName) . "</td></tr>\n";
            } else {
                $out .= "<td>" . $currency . " " . $row[Amount] . "</td><td>Buy_Land</td>";
                $out .= "<td>User " . username($row[FromID], $dbUsers, $dbUsersName) . "bought land from you</td></tr>\n";
            }
            break;
        case 9000: // LindenAdjustment
            if ($row[Amount] < 0) {
                $out .= "<td>" . $currency . " " . $row[Amount] . "</td><td>Sell_Currency</td>";
                $out .= "<td>You sold in-world currency</td></tr>\n";
            } else {
                $out .= "<td>" . $currency . " " . $row[Amount] . "</td><td>Buy_Currency</td>";
                $out .= "<td>You bought in-world currency</td></tr>\n";
            }
            break;
        default:
            $out .= "<td>" . $currency . " " . $row[Amount] . "</td><td>Unknown</td>";
            $out .= "<td>Unknown transaction type " . $row[Type] . "</td></tr>\n";
    }
}

$out .= "</table>\n</body>\n</html>\n";

if ($email) {
    // send email
    $header  = "MIME-Version: 1.0\r\n";
    $header .= "Content-Type: text/html; charset=iso-8859-1\r\n";
    $header .= "Content-Transfer-Encoding: quoted-printable\r\n";
    $header .= "From: " . $fromHeader . "\r\n";
    $header .= "Reply-To: " . $fromHeader . "\r\n";
    $header .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    $header .= "Organization: Dreamland Metaverse";

    $ret = mail($email, "Money Report", $out, $header);

    if ($ret) {
        echo "true";
    } else {
        echo "false";
    }
} else {
    echo $out;
}

function username($agentID, $dbUsers, $dbUsersName)
{
    if ($user_name_cache[$agentID]) return $user_name_cache[$agentID];

    $user_name = "Unknown User";

    mysql_select_db($dbUsersName, $dbUsers);
    $sqlUsers = "SELECT FirstName, LastName, Email FROM UserAccounts WHERE PrincipalID=\"" . $agentID . "\"";
    $resultUsers = mysql_query($sqlUsers, $dbUsers);

    if (!$resultUsers) {
        die('Could not execute database query: ' . mysql_error());
    }

    if ($rowUsers = mysql_fetch_assoc($resultUsers)) {
        $user_name = $rowUsers[FirstName] . " " . $rowUsers[LastName];
    }

    $user_name_cache[$agentID] = $user_name;
    return $user_name;
}
?>
