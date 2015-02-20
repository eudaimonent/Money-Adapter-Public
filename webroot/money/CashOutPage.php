<?php
// CashOutPage  V 0.3  07.09.12
// CashOutPage PW Time Email
// Create a page to be able to cash out money exchanges to a real world currency via PayPal,
// starting with transactions at the given Unix time (default: last day minus 10 minutes).
// Show that page (string returned) or optionally send it to the given email address.
// Copyright by Snoopy Pfeffer, 2012

include('config.php');

// initialize variables

$pw = $_GET["PW"];
$time = $_GET["Time"];
$email = $_GET["Email"];

if ($time == "") {
    $time = time() - 60 * 60 * 24 - 60 * 10; // last day minus 10 minutes
}

// constants

$ttGift = 5001;

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
$out .= "<head><title>Cash Out Page</title></head>\n<body>\n";

// iterate over money exchange requests

mysql_select_db($dbName, $db);
$sql = "SELECT FromID, Amount, Time FROM Transactions WHERE ToID=\"" . $bankAccount . "\" AND Type=" . $ttGift . " AND Time>=" . $time . " ORDER BY Time ASC";
$result = mysql_query($sql, $db);

if (!$result) {
    die('Could not execute database query: ' . mysql_error());
}

$out .= '<table border="1" cellspacing="0" cellpadding="4">';
$out .= "\n<tr><th>Time (UTC)</th><th>User</th><th>Email</th><th>Amount</th><th>Amount</th><th>Pay</th></tr>\n";

$n = 0;

while ($row = mysql_fetch_assoc($result)) {
    $n += 1;
    $ts = $row[Time];
    $date = new DateTime("@$ts");
    $timeString = $date->format('Y-m-d H:i:s');

    $username = username($row[FromID], $dbUsers, $dbUsersName);
    $useremail = useremail($row[FromID], $dbUsers, $dbUsersName);

    $out .= "<tr><td>" . $timeString . " (" . $ts . ")</td><td>" . $username . "</td><td>" . $useremail . "</td>";

    $currencySell = $row[Amount];
    $amount = round($currencySell * (1.0 - $payPalPercentageFee)) * $payPalExchangeRate - $payPalTransactionFee;
    $amountString = sprintf("%01.2f", $amount);

    if ($currencySell >= $minCurrency) {
        $out .= "<td>" . $currency . " " . $currencySell . "</td>";
    } else {
        $out .= "<td><strong>" . $currency . " " . $currencySell . "</strong></td>";
    }

    if ($amount > 0.0) {
        $out .= "<td>" . $payPalCurrency . " " . $amountString . "</td>";

        if ($useremail == "") {
            $out .= "<td><strong>Email?</strong></td></tr>\n";
        } else {
            $sellMsg = $username . " sold " . $currency . " " . $currencySell . " on " . $timeString;
            $ppurl = "https://" . $payPalURL . "/cgi-bin/webscr?cmd=_xclick"
                . "&business=" . urlencode($useremail)
                . "&item_name=" . urlencode($sellMsg)
                . "&item_number=" . urlencode("1")
                . "&amount=" . urlencode($amountString)
                . "&currency_code=" . urlencode($payPalCurrency)
                . "&bn=" . urlencode("PP-BuyNowBF")
                . "&page_style=" . urlencode("Paypal")
                . "&no_note=" . urlencode("0")
                . "&no_shipping=" . urlencode("1")
                . "&charset=" . urlencode("UTF-8");
            $out .= "<td><a href=\"" . $ppurl . "\" target=\"_blank\">Pay</a></td></tr>\n";
        }
    } else {
        $out .= "<td><strong>" . $payPalCurrency . " " . $amountString . "</strong></td>";
        $out .= "<td>&nbsp;</td></tr>\n";
    }
}

$out .= "</table>\n</body>\n</html>\n";

if ($email) {
    if ($n == 0) {
        echo "true";
        return;
    }

    // send email
    $header  = "MIME-Version: 1.0\r\n";
    $header .= "Content-Type: text/html; charset=iso-8859-1\r\n";
    $header .= "Content-Transfer-Encoding: quoted-printable\r\n";
    $header .= "From: " . $fromHeader . "\r\n";
    $header .= "Reply-To: " . $fromHeader . "\r\n";
    $header .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    $header .= "Organization: Dreamland Metaverse";

    $ret = mail($email, "Cash Out Page", $out, $header);

    if ($ret) {
        echo "true";
    } else {
        echo "false";
    }
} else {
    if ($n > 0) echo $out;
}

function username($agentID, $dbUsers, $dbUsersName)
{
    if ($user_name_cache[$agentID]) return $user_name_cache[$agentID];

    $user_name = "Unknown User";
    $user_email = "";

    mysql_select_db($dbUsersName, $dbUsers);
    $sqlUsers = "SELECT FirstName, LastName, Email FROM UserAccounts WHERE PrincipalID=\"" . $agentID . "\"";
    $resultUsers = mysql_query($sqlUsers, $dbUsers);

    if (!$resultUsers) {
        die('Could not execute database query: ' . mysql_error());
    }

    if ($rowUsers = mysql_fetch_assoc($resultUsers)) {
        $user_name = $rowUsers[FirstName] . " " . $rowUsers[LastName];
        $user_email = $rowUsers[Email];
    }

    $user_name_cache[$agentID] = $user_name;
    $user_email_cache[$agentID] = $user_email;
    return $user_name;
}

function useremail($agentID, $dbUsers, $dbUsersName)
{
    if ($user_email_cache[$agentID]) return $user_email_cache[$agentID];

    $user_name = "Unknown User";
    $user_email = "";

    mysql_select_db($dbUsersName, $dbUsers);
    $sqlUsers = "SELECT FirstName, LastName, Email FROM UserAccounts WHERE PrincipalID=\"" . $agentID . "\"";
    $resultUsers = mysql_query($sqlUsers, $dbUsers);

    if (!$resultUsers) {
        die('Could not execute database query: ' . mysql_error());
    }

    if ($rowUsers = mysql_fetch_assoc($resultUsers)) {
        $user_name = $rowUsers[FirstName] . " " . $rowUsers[LastName];
        $user_email = $rowUsers[Email];
    }

    $user_name_cache[$agentID] = $user_name;
    $user_email_cache[$agentID] = $user_email;
    return $user_email;
}
?>
