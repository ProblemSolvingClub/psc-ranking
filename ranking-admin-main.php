<?php
if (!defined('IN_PSC_RANKING_ADMIN')) die;
?>
<table border=1>
<tr>
<td valign=top>
<h2>Add Meeting</h2>
<form method="post">
<input type="hidden" name="action" value="add_meeting">
<label>Date (yyyy-mm-dd): <input type="text" name="date" value="<?php echo strftime('%Y-%m-%d'); ?>"></label><br>
<label>Kattis contest ID (optional): <input type="text" name="kattis_contest_id"></label><br>
<input type="submit" value="Add Meeting">
</form>
</td>
<td valign=top>
<h2>Add User</h2>
<form method="post">
<input type="hidden" name="action" value="add_user">
<label>First name: <input type="text" name="first_name"></label><br>
<label>Last name: <input type="text" name="last_name"></label><br>
<input type="submit" value="Add User">
</form>
</td>
<td valign=top>
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
</td>
<td valign=top>
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
</td>
</tr>
</table>
<h2>Meeting List</h2>
<table border="1">
<tr><th>Date</th><th>Kattis Contest ID</th></tr>
<?php
$meetings = $db->query('SELECT id, date, kattis_contest_id FROM meeting ORDER BY date DESC')->fetchAll();
$kattis_contest_name_sth = $db->prepare('SELECT kattis_contest_name FROM kattis_contest WHERE kattis_contest_id=?');
foreach ($meetings as $meeting) {
	$kattis_contest_name_sth->execute(array($meeting['kattis_contest_id']));
	$contest_desc = $meeting['kattis_contest_id'];
	if ($row = $kattis_contest_name_sth->fetch()) {
		$contest_desc = "<a href=\"https://open.kattis.com/contests/{$meeting['kattis_contest_id']}\">{$row['kattis_contest_name']} ({$meeting['kattis_contest_id']})</a>";
	}
	echo "<tr>";
	echo "<td><a href='ranking-admin.php?meeting_id={$meeting['id']}'>{$meeting['date']}</a></td>";
	echo "<td>$contest_desc</td>";
	echo "</tr>\n";
}
?>
</table>
<h2>User List</h2>
<table border="1">
<tr><th>Name</th><th>Attended<br>Meetings</th><th>Bonus<br>Problems</th><th>Website</th><th>Username</th><th>Solved</th><th>Last Updated (UTC)</th></tr>
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
	$rowspan = max(1,count($accounts));
	echo "<tr><td rowspan=$rowspan>{$user['first_name']} {$user['last_name']}";
	if ($user['unofficial']) echo '<br>(Unofficial)</br>';
	echo "</td>\n";
	echo "<td rowspan=$rowspan>{$user['meeting_count']}</td>";
	echo "<td rowspan=$rowspan>{$user['bonus_count']}</td>";
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
	if (empty($accounts)) echo "</tr>\n";
}
?>
</table>
