<?php
ini_set('display_errors', 'On');

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
		die('Insufficent parameters');
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

function action_add_user() {
	global $db, $status_message;
	if (!(isset($_POST['first_name']) && isset($_POST['last_name']))) {
		die('Insufficent parameters');
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
		die('Insufficent parameters');
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

if (isset($_POST['action'])) {
	if ($_POST['action'] == 'add_account') action_add_account();
	elseif ($_POST['action'] == 'add_user') action_add_user();
	elseif ($_POST['action'] == 'toggle_unofficial') action_toggle_unofficial();
	else $status_message = 'Invalid action.';
}

// Users with scores.
// [ { id, firstName, lastName, totalSolved, siteSolved: [{siteId: solved}] } ]
$users_sql = 'SELECT id, first_name, last_name, unofficial FROM user';
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
<h1>PSC Ranking Admin</h1>
<h2>Add User</h2>
<form method="post">
<input type="hidden" name="action" value="add_user">
<label>First name: <input type="text" name="first_name"></label><br>
<label>Last name: <input type="text" name="last_name"></label><br>
<input type="submit" value="Add User">
</form>
<h2>Add Account</h2>
<form method="post">
<input type="hidden" name="action" value="add_account">
<label>User: <select name="user_id">
<?php
foreach ($users as $user) {
	echo "<option value=\"{$user['id']}\">{$user['first_name']} {$user['last_name']}</option>\n";
}
?>
</select></label><br>
<label>Site: <select name="site_id">
<?php
foreach ($sites as $site_id => $site) {
	echo "<option value=\"$site_id\">{$site['name']}</option>\n";
}
?>
</select></label><br>
<label>Username: <input type="text" name="username"></label><br>
<input type="submit" value="Add User">
</form>
<h2>Toggle Unofficial</h2>
<form method="post">
<input type="hidden" name="action" value="toggle_unofficial">
<label>User: <select name="user_id">
<?php
foreach ($users as $user) {
	echo "<option value=\"{$user['id']}\">{$user['first_name']} {$user['last_name']}</option>\n";
}
?>
</select></label>
<input type="submit" value="Toggle Unofficial">
</form>
<h2>User List</h2>
<table border="1">
<tr><th>Name</th><th>Website</th><th>Username</th><th>Solved</th><th>Last Updated (UTC)</th></tr>
<?php
$sites_sth = $db->prepare('SELECT site_id, username FROM site_account WHERE user_id=?');
$score_sth = $db->prepare('SELECT solved, created_date FROM site_score WHERE site_id=? AND username=? ORDER BY created_date DESC LIMIT 1');
foreach ($users as $user) {
	$sites_sth->execute(array($user['id']));
	$accounts = $sites_sth->fetchAll();
	uasort($accounts, function($a, $b) use ($sites) {
		$av = $sites[$a['site_id']]['name'];
		$bv = $sites[$b['site_id']]['name'];
		if ($av < $bv) return -1;
		elseif ($av == $bv) return 0;
		else return 1;
	});
	echo "<tr><td rowspan=" . count($accounts) . ">{$user['first_name']} {$user['last_name']}";
	if ($user['unofficial']) echo '<br>(Unofficial)</br>';
	echo "</td>\n";
	$first = true;
	foreach ($accounts as $account) {
		$solved = '';
		$last_updated = 'Never';
		$score_sth->execute(array($account['site_id'], $account['username']));
		$score = $score_sth->fetch();
		if ($score) {
			$solved = $score['solved'];
			$last_updated = $score['created_date'];
		}

		if (!$first) echo "<tr>";
		$first = false;
		$site = $sites[$account['site_id']];
		$url = sprintf($site['profile_url'], $account['username']);
		echo "<td>{$site['name']}</td>";
		echo "<td><a target=\"_blank\" href=\"$url\">{$account['username']}</a></td>";
		echo "<td>$solved</td>";
		echo "<td>$last_updated</td>";
		echo "</tr>\n";
	}
}
?>
</table>
</body>
</html>
