<?php
if (!defined('IN_PSC_RANKING_ADMIN')) die;
?>
<table border=1 class="form_panel">
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
	if ($user['deleted']) continue;
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
</tr>
</table>
<h2>Semester Start Date</h2>
<?php
$semesterStartDate = $db->query('SELECT value FROM setting WHERE name=\'semester_start_date\'')->fetch()[0];
echo 'The semester start date is <b>' . htmlspecialchars($semesterStartDate) . '</b>.';
?>
<form method="post">
<input type="hidden" name="action" value="change_semester_start_date">
<label>New date (yyyy-mm-dd): <input type="text" name="date" value="<?php echo strftime('%Y-%m-%d'); ?>" size="10"></label>
<input type="submit" value="Submit">
</form>
<h2>Meeting List</h2>
<table border="1">
<tr><th>Date</th><th>Kattis Contest ID</th><th>Last Scraped</th></tr>
<?php
$meetings = $db->query('SELECT id, date, meeting.kattis_contest_id, kattis_contest_name, kattis_contest.created_date FROM meeting LEFT JOIN kattis_contest ON meeting.kattis_contest_id=kattis_contest.kattis_contest_id WHERE NOT meeting.deleted ORDER BY date DESC')->fetchAll();
foreach ($meetings as $meeting) {
	$contest_desc = $meeting['kattis_contest_id'];
	$last_scraped = 'Never';
	if ($meeting['kattis_contest_name'] !== null) {
		$contest_desc = "<a href=\"https://open.kattis.com/contests/{$meeting['kattis_contest_id']}\">{$meeting['kattis_contest_name']} ({$meeting['kattis_contest_id']})</a>";
		$last_scraped = $meeting['created_date'];
	}
	$date_str = date_with_dow($meeting['date']);
	echo "<tr>";
	echo "<td><a href='ranking-admin.php?meeting_id={$meeting['id']}'>$date_str</a></td>";
	echo "<td>$contest_desc</td>";
	echo "<td>$last_scraped</td>";
	echo "</tr>\n";
}
?>
</table>
<h2>User List</h2>
<p><a href="ranking-admin.php?deleted_user_list">View deleted users</a></p>
<?php require('ranking-admin-user-list.php'); ?>
