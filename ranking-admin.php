<?php
ini_set('display_errors', 'On');
define('IN_PSC_RANKING_ADMIN', true);
date_default_timezone_set('America/Edmonton');

$db = new PDO('sqlite:/home/pscadmin/psc-ranking/ranking.sqlite3');

// Sites.
$sites = array();
$sites_sql = 'SELECT id, name, profile_url FROM site';
foreach ($db->query($sites_sql) as $row) {
    $sites[$row['id']] = $row;
}

function action_add_account() {
	global $db, $status_message;
	if (!(isset($_POST['user_id']) && isset($_POST['site_id']) && isset($_POST['username']))) {
		$status_message = 'Insufficent parameters';
		return;
	}
	$user_id = $_POST['user_id'];
	$site_id = $_POST['site_id'];
	$username = trim($_POST['username']);
	if (empty($username)) die('Must provide username');

	// Insert data
	$sth = $db->prepare('INSERT INTO site_account (user_id, site_id, username) VALUES (?, ?, ?)');
	if ($sth->execute(array($user_id, $site_id, $username))) {
		$status_message = 'Account added successfully.';
	} else {
		$status_message = 'Unable to add account.';
	}
}

function action_add_meeting() {
	global $db, $status_message;
	if (!(isset($_POST['date'])) || !preg_match('/^[0-9]{4}\-[0-9]{2}\-[0-9]{2}$/', $_POST['date'])) {
		$status_message = 'Invalid date.';
		return;
	}
	$kattis_contest_id = null;
	if (isset($_POST['kattis_contest_id']) && !empty($_POST['kattis_contest_id'])) {
		$kattis_contest_id = $_POST['kattis_contest_id'];
		if (!preg_match('/^[a-z0-9]+$/', $kattis_contest_id)) {
			$status_message = 'Invalid Kattis contest ID.';
			return;
		}
	}

	// Insert data
	$sth = $db->prepare('INSERT INTO meeting (date, kattis_contest_id) VALUES (?, ?)');
	if ($sth->execute(array($_POST['date'], $kattis_contest_id))) {
		$status_message = 'Meeting added successfully.';
	} else {
		$status_message = 'Unable to add meeting.';
	}
}

function action_add_user() {
	global $db, $status_message;
	if (!(isset($_POST['first_name']) && isset($_POST['last_name']))) {
		$status_message = 'Insufficent parameters';
		return;
	}
	$first_name = trim($_POST['first_name']);
	$last_name = trim($_POST['last_name']);
	if (empty($first_name) || empty($last_name)) die('Must provide first and last name');

	// Insert data
	$sth = $db->prepare('INSERT INTO user (first_name, last_name) VALUES (?, ?)');
	if ($sth->execute(array($first_name, $last_name))) {
		$status_message = 'User added successfully.';
	} else {
		$status_message = 'Unable to add user.';
	}
}

function action_toggle_unofficial() {
	global $db, $status_message;
	if (!isset($_POST['user_id'])) {
		$status_message = 'Insufficent parameters';
		return;
	}
	$user_id = $_POST['user_id'];

	// Perform toggle
	$sth = $db->prepare('UPDATE user SET unofficial=NOT unofficial WHERE id=?');
	if ($sth->execute(array($user_id)) && $sth->rowCount() == 1) {
		$status_message = 'User unofficial status togged successfully.';
	} else {
		$status_message = 'Unable to toggle user unofficial status.';
	}
}

function action_update_meeting_attendance() {
	global $db, $status_message;
	if (!isset($_POST['meeting_id'])) {
		$status_message = 'Insufficent parameters';
		return;
	}
	$meeting_id = $_POST['meeting_id'];

	// First, delete all existing attendance
	$sth = $db->prepare('DELETE FROM meeting_attended WHERE meeting_id=?');
	if (!$sth->execute(array($meeting_id))) {
		$status_message = 'Unable to delete all existing attendance.';
		return;
	}

	// Next, insert al new attendance
	$sth = $db->prepare('INSERT INTO meeting_attended (meeting_id, user_id) VALUES (?,?)');
	foreach ($_POST as $user_id => $val) {
		if (preg_match('/^[0-9]+$/', $user_id) && !empty($val)) {
			if (!$sth->execute(array($meeting_id, $user_id))) {
				$status_message = 'Unexpected error updating attendance.';
				return;
			}
		}
	}
	$status_message = 'Attendance updated successfully.';
}

if (isset($_POST['action'])) {
	if ($_POST['action'] == 'add_account') action_add_account();
	elseif ($_POST['action'] == 'add_meeting') action_add_meeting();
	elseif ($_POST['action'] == 'add_user') action_add_user();
	elseif ($_POST['action'] == 'toggle_unofficial') action_toggle_unofficial();
	elseif ($_POST['action'] == 'update_meeting_attendance') action_update_meeting_attendance();
	else $status_message = 'Invalid action.';
}

// Users with scores.
// [ { id, firstName, lastName, totalSolved, siteSolved: [{siteId: solved}] } ]
$users_sql = 'SELECT id, first_name, last_name, unofficial, (SELECT COUNT(*) FROM meeting_attended WHERE user_id=id) AS meeting_count, (SELECT COUNT(*) FROM kattis_contest_solved WHERE kattis_username IN (SELECT username FROM site_account WHERE site_id=5 AND user_id=id)) AS bonus_count FROM user';
$users = $db->query($users_sql)->fetchAll();
// Sort by reverse order of Total
uasort($users, function($a, $b) {
	$av = "{$a['first_name']} {$a['last_name']}";
	$bv = "{$b['first_name']} {$b['last_name']}";
	if ($av < $bv) return -1;
	elseif ($av == $bv) return 0;
	else return 1;
});

// Get all user IDs.
?>
<!doctype html>
<html>
<head>
<title>PSC Ranking Admin</title>
<style>
body {
	font: 10pt arial;
}
</style>
</head>
<body>
<?php
if (isset($status_message)) {
	echo "<div style=\"padding: 5px; border: 3px inset green;\">$status_message</div>";
}
?>
<h1><a href="ranking-admin.php">PSC Ranking Admin</a></h1>
<?php
if (isset($_GET['meeting_id'])) {
	require('ranking-admin-meeting.php');
} else {
	require('ranking-admin-main.php');
}
?>
</body>
</html>
