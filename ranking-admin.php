<?php
ini_set('display_errors', 'On');
define('IN_PSC_RANKING_ADMIN', true);
date_default_timezone_set('America/Edmonton');

$db = new PDO('sqlite:/home/pscadmin/psc-ranking/ranking.sqlite3');
session_start();
$logged_in_user = require_login();

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
	global $db, $logged_in_user, $status_message;
	if (!(isset($_POST['first_name']) && isset($_POST['last_name']))) {
		$status_message = 'Insufficent parameters';
		return;
	}
	$first_name = trim($_POST['first_name']);
	$last_name = trim($_POST['last_name']);
	if (empty($first_name) || empty($last_name)) die('Must provide first and last name');

	// Insert data
	$sth = $db->prepare('INSERT INTO user (first_name, last_name, created_user_id) VALUES (?, ?, ?)');
	if ($sth->execute(array($first_name, $last_name, $logged_in_user['id']))) {
		$status_message = 'User added successfully.';
	} else {
		$status_message = 'Unable to add user.';
	}
}
function action_change_ucid() {
	global $db, $status_message;
	if (!isset($_POST['user_id']) || !isset($_POST['ucid']) || !preg_match('/^[0-9]{6,8}$/', $_POST['ucid'])) {
		$status_message = 'Insufficent or invalid parameters.';
		return;
	}
	$user_id = $_POST['user_id'];
	$ucid = $_POST['ucid'];

	// Perform change
	$sth = $db->prepare("UPDATE user SET ucid=? WHERE id=?");
	if ($sth->execute(array($ucid, $user_id)) && $sth->rowCount() == 1) {
		$status_message = 'User UCID changed successfully.';
	} else {
		$status_message = 'Unable to change user UCID.';
	}
}

function action_toggle_unofficial_admin() {
	global $db, $status_message;
	$cols = array('Toggle Admin' => 'admin', 'Toggle Unofficial' => 'unofficial');
	if (!isset($_POST['user_id']) || !isset($_POST['submit']) || !array_key_exists($_POST['submit'], $cols)) {
		$status_message = 'Insufficent parameters';
		return;
	}
	$user_id = $_POST['user_id'];
	$col = $cols[$_POST['submit']];

	// Perform toggle
	$sth = $db->prepare("UPDATE user SET $col=NOT $col WHERE id=?");
	if ($sth->execute(array($user_id)) && $sth->rowCount() == 1) {
		$status_message = 'User flag toggled successfully.';
	} else {
		$status_message = 'Unable to toggle user flag.';
	}
}

function get_meeting_attendance($meeting_id) {
	global $db;
	$attendance_sth = $db->prepare('SELECT user_id, attended FROM meeting_attended WHERE meeting_id=? ORDER BY created_date DESC');
	$attendance_sth->execute(array($meeting_id));
	$attended_user_ids = array();
	while ($row = $attendance_sth->fetch()) {
		if (!array_key_exists($row['user_id'], $attended_user_ids)) {
			$attended_user_ids[$row['user_id']] = $row['attended'];
		}
	}
	return $attended_user_ids;
}

function action_update_meeting_attendance() {
	global $db, $logged_in_user, $status_message;
	if (!isset($_POST['meeting_id'])) {
		$status_message = 'Insufficent parameters';
		return;
	}
	$meeting_id = $_POST['meeting_id'];

	// First, get existing attendance
	$attended_user_ids = get_meeting_attendance($meeting_id);

	// Get all user IDs
	$users = $db->query('SELECT id FROM user')->fetchAll();

	// Next, insert new attendance if different
	$sth = $db->prepare('INSERT INTO meeting_attended (meeting_id, user_id, attended, created_user_id) VALUES (?,?,?,?)');
	foreach ($users as $user) {
		$attended = array_key_exists($user['id'], $_POST) && !empty($_POST[$user['id']]);
		$attended_prev_value = array_key_exists($user['id'], $attended_user_ids) && $attended_user_ids[$user['id']];
		if ($attended != $attended_prev_value) {
			// Insert new attendance value
			$attended_value = $attended ? 1 : 0;
			if (!$sth->execute(array($meeting_id, $user['id'], $attended_value, $logged_in_user['id']))) {
				$status_message = 'Unexpected error updating attendance.';
				return;
			}
		}
	}
	$status_message = 'Attendance updated successfully.';
}

function require_login() {
	global $db;
	$service_url = 'http://psc.cpsc.ucalgary.ca/ranking-admin.php';
	if (isset($_GET['logout'])) {
		// Destroy session
		unset($_SESSION['psc-ranking-user-id']);

		// Also log out user from CAS
		header('Location: https://cas.ucalgary.ca/cas/logout');
		die;
	}
	if (isset($_GET['ticket'])) {
		// Attempt UofC CAS login
		$validate_query = array('service' => $service_url, 'ticket' => $_GET['ticket']);
		$validate_url = 'https://cas.ucalgary.ca/cas/ucserviceValidate?' . http_build_query($validate_query);
		$validate_xml = new SimpleXMLElement(file_get_contents($validate_url));
		$auth_success = $validate_xml->children('cas', true)->authenticationSuccess;
		if ($auth_success) {
			$cas_persons = $auth_success->ucidList->person;
			$sth = $db->prepare('SELECT id FROM user WHERE ucid=?');
			foreach ($cas_persons as $person) {
				$ucid = (string)$person->ucid;
				$sth->execute(array($ucid));
				$sth_row = $sth->fetch();
				if ($sth_row !== false) {
					// Authentication is successful.
					$_SESSION['psc-ranking-user-id'] = $sth_row['id'];
					header("Location: $service_url");
					die;
				}
			}
		}
		echo 'Authentication failed.';
		die;
	}
	if (isset($_SESSION['psc-ranking-user-id'])) {
		$sth = $db->prepare('SELECT id, first_name, last_name FROM user WHERE id=? AND admin=1');
		$sth->execute(array($_SESSION['psc-ranking-user-id']));
		$sth_row = $sth->fetch();
		if ($sth_row === false) {
			echo 'User is not an admin.';
			die;
		} else {
			// User is an admin
			return $sth_row;
		}
	}
	// Redirect user to UofC CAS login page
	$login_query = array('service' => $service_url);
	$login_url = 'https://cas.ucalgary.ca/cas/login?' . http_build_query($login_query);
	header("Location: $login_url");
	die;
}

if (isset($_POST['action'])) {
	if ($_POST['action'] == 'add_account') action_add_account();
	elseif ($_POST['action'] == 'add_meeting') action_add_meeting();
	elseif ($_POST['action'] == 'add_user') action_add_user();
	elseif ($_POST['action'] == 'change_ucid') action_change_ucid();
	elseif ($_POST['action'] == 'toggle_unofficial_admin') action_toggle_unofficial_admin();
	elseif ($_POST['action'] == 'update_meeting_attendance') action_update_meeting_attendance();
	else $status_message = 'Invalid action.';
}

// Users with scores.
// [ { id, firstName, lastName, totalSolved, siteSolved: [{siteId: solved}] } ]
$users_sql = 'SELECT id, first_name, last_name, ucid, unofficial, admin, (SELECT COUNT(*) FROM meeting_attended WHERE user_id=id) AS meeting_count, (SELECT COUNT(*) FROM kattis_contest_solved WHERE kattis_username IN (SELECT username FROM site_account WHERE site_id=5 AND user_id=id)) AS bonus_count FROM user';
$users = $db->query($users_sql)->fetchAll();
// Sort by reverse order of Total
uasort($users, function($a, $b) {
	$av = "{$a['first_name']} {$a['last_name']}";
	$bv = "{$b['first_name']} {$b['last_name']}";
	if ($av < $bv) return -1;
	elseif ($av == $bv) return 0;
	else return 1;
});
$user_ids_to_index = array();
foreach ($users as $index => $user) $user_ids_to_index[$user['id']] = $index;

// Get all user IDs.
?>
<!doctype html>
<html>
<head>
<title>PSC Ranking Admin</title>
<style>
body, input, select {
	font: 10pt arial;
}
.form_panel input[type="text"] {
	width: 80px;
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
<p>
Welcome <?php echo "{$logged_in_user['first_name']} {$logged_in_user['last_name']}"; ?>!
<a href="ranking-admin.php?logout">Log out</a>
</p>
<?php
if (isset($_GET['meeting_id'])) {
	require('ranking-admin-meeting.php');
} else if (isset($_GET['user_id'])) {
	require('ranking-admin-user.php');
} else {
	require('ranking-admin-main.php');
}
?>
</body>
</html>
