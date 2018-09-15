<?php
ini_set('display_errors', 'On');
define('IN_PSC_RANKING_ADMIN', true);

// Log actions
define('LOG_ACTION_LOGIN', 1);
define('LOG_ACTION_ADD_USER', 2);
define('LOG_ACTION_CHANGE_UCID', 3);
define('LOG_ACTION_CHANGE_UNOFFICIAL', 4);
define('LOG_ACTION_CHANGE_ADMIN', 5);
define('LOG_ACTION_ADD_MEETING', 6);
define('LOG_ACTION_CHANGE_MEETING_ATTENDANCE', 7);
define('LOG_ACTION_ADD_ACCOUNT', 8);
define('LOG_ACTION_DELETE_ACCOUNT', 9);
define('LOG_ACTION_CHANGE_SEMESTER_START_DATE', 10);
define('LOG_ACTION_CHANGE_DELETED', 11);
define('LOG_ACTION_DELETE_MEETING', 12);
define('LOG_ACTION_ALTER_MEETING', 13);
define('LOG_ACTION_CHANGE_FIRST_LAST_NAME', 14);

date_default_timezone_set('America/Edmonton');

$db = new PDO('sqlite:/home/pscadmin/psc-ranking/ranking.sqlite3');
session_start();
$logged_in_user = require_login();
load_globals();

function action_bulk_user_modify() {
	global $db, $logged_in_user, $status_message, $user_ids_to_index, $users;
	if (!(isset($_POST['user_ids']) && isset($_POST['bulk_action']))) {
		$status_message = 'Insufficent or invalid parameters';
		return;
	}
	$bulk_action = $_POST['bulk_action'];
	switch ($bulk_action) {
	case 'make_admin':
		$col = 'admin';
		$value = 1;
		$log_action = LOG_ACTION_CHANGE_ADMIN;
		break;
	case 'make_unadmin':
		$col = 'admin';
		$value = 0;
		$log_action = LOG_ACTION_CHANGE_ADMIN;
		break;
	case 'make_official':
		$col = 'unofficial';
		$value = 0;
		$log_action = LOG_ACTION_CHANGE_UNOFFICIAL;
		break;
	case 'make_unofficial':
		$col = 'unofficial';
		$value = 1;
		$log_action = LOG_ACTION_CHANGE_UNOFFICIAL;
		break;
	case 'delete':
		$col = 'deleted';
		$value = 1;
		$log_action = LOG_ACTION_CHANGE_DELETED;
		break;
	case 'undelete':
		$col = 'deleted';
		$value = 0;
		$log_action = LOG_ACTION_CHANGE_DELETED;
		break;
	default:
		$status_message = 'Invalid bulk_action';
		return;
	}
	$sth = $db->prepare("UPDATE user SET $col=? WHERE id=?");
	if (!$db->beginTransaction()) {
		$status_message = 'Unable to begin transaction';
		return;
	}
	$status_msgs = array();
	$user_ids = explode(',', $_POST['user_ids']);
	foreach ($user_ids as $user_id) {
		if (!array_key_exists($user_id, $user_ids_to_index)) {
			$status_msgs[] = 'Invalid user id ' . htmlspecialchars($user_id);
			continue;
		}
		$user = $users[$user_ids_to_index[$user_id]];
		if ($user[$col] == $value) continue;
		$full_name = htmlspecialchars("{$user['first_name']} {$user['last_name']}");
		if ($bulk_action == 'delete' && $user['admin']) {
			$status_msgs[] = "Cannot delete admin $full_name";
			continue;
		}
		if ($bulk_action != 'undelete' && $user['deleted']) {
			$status_msgs[] = "Cannot perform action on deleted user $full_name";
			continue;
		}
		if (!$sth->execute(array($value, $user_id))) {
			$status_msgs[] = "Action failed for $full_name";
			continue;
		}
		insert_log($logged_in_user['id'], $log_action, $user_id, null, $value);
	}
	if ($db->commit()) {
		$status_message = 'Performed actions successfully';
		if ($status_msgs) {
			$status_message .= '<ul>';
			foreach ($status_msgs as $msg) $status_message .= "<li>$msg</li>";
			$status_message .= '</ul>';
		}
	} else {
		$status_message = 'Failed to commit transaction';
	}
}

function action_add_account() {
	global $db, $logged_in_user, $status_message;
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
		insert_log($logged_in_user['id'], LOG_ACTION_ADD_ACCOUNT, $user_id, null, "$site_id:$username");
		$status_message = 'Account added successfully.';
	} else {
		$status_message = 'Unable to add account.';
	}
}

function action_delete_account() {
	global $db, $logged_in_user, $status_message;
	if (!(isset($_POST['user_id']) && isset($_POST['site_id']) && isset($_POST['username']))) {
		$status_message = 'Insufficent parameters';
		return;
	}
	$user_id = $_POST['user_id'];
	$site_id = $_POST['site_id'];
	$username = $_POST['username'];

	// Insert data
	$sth = $db->prepare('DELETE FROM site_account WHERE user_id=? AND site_id=? AND username=?');
	$sth->bindParam(1, $user_id, PDO::PARAM_INT);
	$sth->bindParam(2, $site_id, PDO::PARAM_INT);
	$sth->bindParam(3, $username, PDO::PARAM_STR);
	if ($sth->execute() && $sth->rowCount() == 1) {
		insert_log($logged_in_user['id'], LOG_ACTION_DELETE_ACCOUNT, $user_id, null, "$site_id:$username");
		$status_message = 'Account deleted successfully.';
	} else {
		$status_message = 'Unable to delete account.';
	}
}

function action_add_meeting() {
	global $db, $logged_in_user, $status_message;
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
		insert_log($logged_in_user['id'], LOG_ACTION_ADD_MEETING, null, $db->lastInsertId(), $kattis_contest_id);
		$status_message = 'Meeting added successfully.';
	} else {
		$status_message = 'Unable to add meeting.';
	}
}

function action_change_meeting_properties() {
	global $db, $logged_in_user, $status_message;
	if (!(isset($_POST['meeting_id'])) ||!(isset($_POST['date'])) || !preg_match('/^[0-9]{4}\-[0-9]{2}\-[0-9]{2}$/', $_POST['date'])) {
		$status_message = 'Invalid parameters.';
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
	$meeting_id = $_POST['meeting_id'];

	$get_sth = $db->prepare('SELECT date, kattis_contest_id FROM meeting WHERE id=? AND deleted=0');
	$get_sth->execute(array($meeting_id));
	$meeting_row = $get_sth->fetch();
	if ($meeting_row === false) die('Invalid meeting ID');
	if ($meeting_row['date'] == $_POST['date'] && $meeting_row['kattis_contest_id'] == $kattis_contest_id) {
		$status_message = 'No changes made.';
		return;
	}

	$sth = $db->prepare('UPDATE meeting SET date=?, kattis_contest_id=? WHERE id=?');
	if ($sth->execute(array($_POST['date'], $kattis_contest_id, $meeting_id)) && $sth->rowCount() == 1) {
		insert_log($logged_in_user['id'], LOG_ACTION_ALTER_MEETING, null, $meeting_id, $_POST['date'] . ':' . $kattis_contest_id);
		$status_message = 'Meeting properties changed successfully.';
	} else {
		$status_message = 'Unable to change meeting properties.';
	}
}

function action_delete_meeting() {
	global $db, $logged_in_user, $status_message;
	if (!isset($_POST['meeting_id'])) {
		$status_message = 'Insufficent parameters';
		return;
	}
	$meeting_id = $_POST['meeting_id'];
	$sth = $db->prepare('UPDATE meeting SET deleted=1 WHERE id=?');
	if ($sth->execute(array($meeting_id)) && $sth->rowCount() == 1) {
		insert_log($logged_in_user['id'], LOG_ACTION_DELETE_MEETING, null, $meeting_id, null);
		$status_message = 'Meeting deleted successfully.';
	} else {
		$status_message = 'Unable to delete meeting.';
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
	if (empty($first_name) || empty($last_name)) {
		$status_message = 'Must provide first and last name';
		return;
	}

	// Insert data
	$sth = $db->prepare('INSERT INTO user (first_name, last_name) VALUES (?, ?)');
	if ($sth->execute(array($first_name, $last_name))) {
		insert_log($logged_in_user['id'], LOG_ACTION_ADD_USER, $db->lastInsertId());
		$status_message = 'User added successfully.';
	} else {
		$status_message = 'Unable to add user.';
	}
}

function action_change_user_properties() {
	global $db, $logged_in_user, $status_message, $user_ids_to_index, $users;
	if (!isset($_POST['user_id']) || !isset($_POST['first_name']) || !isset($_POST['last_name'])) {
		$status_message = 'Invalid parameters.';
		return;
	}
	$first_name = trim($_POST['first_name']);
	$last_name = trim($_POST['last_name']);
	if (empty($first_name) || empty($last_name)) {
		$status_message = 'Must provide first and last name';
		return;
	}
	$ucid = null;
	if (isset($_POST['ucid']) && !empty($_POST['ucid'])) {
		$ucid = $_POST['ucid'];
		if (!preg_match('/^[0-9]{6,8}$/', $ucid)) {
			$status_message = 'Invalid UCID';
			return;
		}
	}
	$user_id = $_POST['user_id'];

	if (!array_key_exists($user_id, $user_ids_to_index)) {
		$status_message = 'Invalid user id.';
		return;
	}
	$user = $users[$user_ids_to_index[$user_id]];
	if ($user['first_name'] == $first_name && $user['last_name'] == $last_name && $user['ucid'] == $ucid) {
		$status_message = 'No changes made.';
		return;
	}
	if ($user['deleted']) {
		$status_message = 'Cannot modify deleted user.';
		return;
	}

	$sth = $db->prepare('UPDATE user SET first_name=?, last_name=?, ucid=? WHERE id=?');
	if ($sth->execute(array($first_name, $last_name, $ucid, $user_id)) && $sth->rowCount() == 1) {
		if ($user['first_name'] != $first_name || $user['last_name'] != $last_name) {
			insert_log($logged_in_user['id'], LOG_ACTION_CHANGE_FIRST_LAST_NAME, $user_id, null, "$first_name $last_name");
		}
		if ($user['ucid'] != $ucid) {
			insert_log($logged_in_user['id'], LOG_ACTION_CHANGE_UCID, $user_id, null, $ucid);
		}
		$status_message = 'User properties changed successfully.';
	} else {
		$status_message = 'Unable to change user properties.';
	}
}

function get_meeting_attendance($meeting_id) {
	global $db;
	$attendance_sth = $db->prepare('SELECT user_id FROM meeting_attended WHERE meeting_id=?');
	$attendance_sth->execute(array($meeting_id));
	$attended_user_ids = array();
	while ($row = $attendance_sth->fetch()) {
		$attended_user_ids[$row['user_id']] = true;
	}
	return $attended_user_ids;
}

function action_update_meeting_attendance() {
	global $db, $logged_in_user, $status_message;
	if (!isset($_POST['meeting_id']) || !isset($_POST['attendance'])) {
		$status_message = 'Insufficent parameters';
		return;
	}
	$meeting_id = $_POST['meeting_id'];

	if (!$db->beginTransaction()) {
		$status_message = 'Unable to begin transaction';
		return;
	}

	// First, get existing attendance
	$attended_user_ids = get_meeting_attendance($meeting_id);

	// Next, insert or delete attendance if different
	$insert_sth = $db->prepare('INSERT INTO meeting_attended (meeting_id, user_id) VALUES (?,?)');
	$delete_sth = $db->prepare('DELETE FROM meeting_attended WHERE meeting_id=? AND user_id=?');
	$attendance = explode(',', $_POST['attendance']);
	foreach ($attendance as $attendance_str) {
		if (!preg_match('/^(\d+)=([01])$/', $attendance_str, $matches)) {
			$status_message = 'Invalid attendance str.';
			return;
		}
		$user_id = $matches[1];
		$attended = $matches[2] === '1';
		$attended_prev_value = array_key_exists($user_id, $attended_user_ids);
		if ($attended != $attended_prev_value) {
			// Insert new attendance value
			$attended_value = $attended ? 1 : 0;
			$sth = $attended_value ? $insert_sth : $delete_sth;
			if (!$sth->execute(array($meeting_id, $user_id))) {
				$status_message = 'Unexpected error updating attendance.';
				return;
			}
			insert_log($logged_in_user['id'], LOG_ACTION_CHANGE_MEETING_ATTENDANCE, $user_id, $meeting_id, $attended_value);
		}
	}
	if ($db->commit()) {
		$status_message = 'Attendance updated successfully.';
	} else {
		$status_message = 'Failed to update attendance.';
	}
}

function action_change_semester_start_date() {
	global $db, $logged_in_user, $status_message;
	if (!(isset($_POST['date'])) || !preg_match('/^[0-9]{4}\-[0-9]{2}\-[0-9]{2}$/', $_POST['date'])) {
		$status_message = 'Invalid date.';
		return;
	}
	$sth = $db->prepare('INSERT OR REPLACE INTO setting (name, value) VALUES (\'semester_start_date\', ?)');
	if ($sth->execute(array($_POST['date']))) {
		insert_log($logged_in_user['id'], LOG_ACTION_CHANGE_SEMESTER_START_DATE, null, null, $_POST['date']);
		$status_message = 'Semester start date changed successfully.';
	} else {
		$status_message = 'Unable to add change semester start date.';
	}
}

function insert_log($user_id, $action, $target_user_id=null, $target_meeting_id=null, $value=null) {
	global $db;
	$sth = $db->prepare("INSERT INTO log (user_id, action, target_user_id, target_meeting_id, value) VALUES (?, ?, ?, ?, ?)");
	if (!$sth->execute(array($user_id, $action, $target_user_id, $target_meeting_id, $value))) {
		die('Failed insert_log');
	}
}

function date_with_dow($date_str) {
	if ($parsed_date = date_create($date_str)) {
		$date_str = date_format($parsed_date, 'Y-m-d (D)');
	}
	return $date_str;
}

function load_globals() {
	global $db, $sites, $user_ids_to_index, $users;
	// Sites.
	$sites = array();
	$sites_sql = 'SELECT id, name, profile_url FROM site';
	foreach ($db->query($sites_sql) as $row) {
		$sites[$row['id']] = $row;
	}

	// Users with scores.
	// [ { id, firstName, lastName, totalSolved, siteSolved: [{siteId: solved}] } ]
	$users_sql = 'SELECT id, first_name, last_name, ucid, unofficial, admin, deleted, (SELECT COUNT(*) FROM meeting_attended WHERE user_id=id) AS meeting_count, (SELECT COUNT(*) FROM kattis_contest_solved WHERE kattis_username IN (SELECT username FROM site_account WHERE site_id=5 AND user_id=id)) AS bonus_count FROM user';
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
}

function require_login() {
	global $db;
	$service_url = "http://{$_SERVER['HTTP_HOST']}/ranking-admin.php";
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
					insert_log($sth_row['id'], LOG_ACTION_LOGIN);
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
	elseif ($_POST['action'] == 'delete_meeting') action_delete_meeting();
	elseif ($_POST['action'] == 'change_meeting_properties') action_change_meeting_properties();
	elseif ($_POST['action'] == 'add_user') action_add_user();
	elseif ($_POST['action'] == 'change_user_properties') action_change_user_properties();
	elseif ($_POST['action'] == 'delete_account') action_delete_account();
	elseif ($_POST['action'] == 'update_meeting_attendance') action_update_meeting_attendance();
	elseif ($_POST['action'] == 'change_semester_start_date') action_change_semester_start_date();
	elseif ($_POST['action'] == 'bulk_user_modify') action_bulk_user_modify();
	else $status_message = 'Invalid action.';
}

load_globals(); // reload in case something changed due to the action

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
.inline_list {
	padding: 0;
}
.inline_list li {
	display: inline;
}
</style>
<script>
function checkAll(table_id, checked) {
	var inputs = document.getElementById(table_id).getElementsByTagName('input');
	for (var i = 0; i < inputs.length; i++) {
		if (inputs[i].type == 'checkbox' && inputs[i].name) {
			inputs[i].checked = checked;
		}
	}
}
function bulkUserModify(table_id, bulk_action) {
	var user_ids = [];
	var inputs = document.getElementById(table_id).getElementsByTagName('input');
	for (var i = 0; i < inputs.length; i++) {
		if (inputs[i].type == 'checkbox' && inputs[i].name && inputs[i].checked) {
			user_ids.push(inputs[i].name);
		}
	}
	if (user_ids.length === 0) return;
	if (!confirm("Are you sure? Perform " + bulk_action + 
		" for "+ user_ids.length + " users?")) return;
	var form = document.getElementById('bulk_user_modify_form');
	form.bulk_action.value = bulk_action;
	form.user_ids.value = user_ids.join(',');
	form.submit();
}
function deleteAcct(user_id, full_name, site_id, site_name, username) {
	if (!confirm("Are you sure? Delete " + site_name + 
		" account "+username+
		" for user "+full_name+" ("+user_id+")")) return;
	var form = document.getElementById('delete_account_form');
	form.user_id.value = user_id;
	form.site_id.value = site_id;
	form.username.value = username;
	form.submit();
}
function updateMeetingAttendance(table_id, meeting_id) {
	var attendance = [];
	var inputs = document.getElementById(table_id).getElementsByTagName('input');
	for (var i = 0; i < inputs.length; i++) {
		if (inputs[i].type == 'checkbox' && inputs[i].name) {
			var val = inputs[i].checked ? '1' : '0';
			attendance.push(inputs[i].name + '=' + val);
		}
	}
	if (attendance.length === 0) return;
	var form = document.getElementById('update_meeting_attendance_form');
	form.meeting_id.value = meeting_id;
	form.attendance.value = attendance.join(',');
	form.submit();
}
</script>
</head>
<body>
<form method="post" id="bulk_user_modify_form">
<input type="hidden" name="action" value="bulk_user_modify">
<input type="hidden" name="bulk_action">
<input type="hidden" name="user_ids">
</form>
<form method="post" id="delete_account_form">
<input type="hidden" name="action" value="delete_account">
<input type="hidden" name="user_id">
<input type="hidden" name="site_id">
<input type="hidden" name="username">
</form>
<form method="post" id="update_meeting_attendance_form">
<input type="hidden" name="action" value="update_meeting_attendance">
<input type="hidden" name="meeting_id">
<input type="hidden" name="attendance">
</form>
<?php
if (isset($status_message)) {
	echo "<div style=\"padding: 5px; border: 3px inset green;\">$status_message</div>";
}
?>
<h1><a href="ranking-admin.php">PSC Ranking Admin</a></h1>
<p>
Welcome <?php echo "{$logged_in_user['first_name']} {$logged_in_user['last_name']}"; ?>!
<a href="ranking-admin.php?log">View log</a>
<a href="ranking-admin.php?logout">Log out</a>
</p>
<?php
if (isset($_GET['meeting_id'])) {
	require('ranking-admin-meeting.php');
} else if (isset($_GET['user_id'])) {
	require('ranking-admin-user.php');
} else if (isset($_GET['log'])) {
	require('ranking-admin-log.php');
} else if (isset($_GET['deleted_user_list'])) {
	define('USER_LIST_DELETED', true);
	echo '<h2>Deleted Users</h2>';
	require('ranking-admin-user-list.php');
} else if (isset($_GET['all_meeting_list'])) {
	define('ALL_MEETING_LIST', true);
	echo '<h2>All Meetings</h2>';
	require('ranking-admin-meeting-list.php');
} else {
	require('ranking-admin-main.php');
}
?>
</body>
</html>
