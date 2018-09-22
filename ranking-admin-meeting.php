<?php
if (!defined('IN_PSC_RANKING_ADMIN')) die;
$meeting_id = $_GET['meeting_id'];
$meeting_sth = $db->prepare('SELECT date, kattis_contest_id FROM meeting WHERE id=? AND deleted=0');
$meeting_sth->execute(array($meeting_id));
$meeting_row = $meeting_sth->fetch();
if ($meeting_row === false) die('Invalid meeting ID');

$attendance = get_meeting_attendance($meeting_id);

$get_kattis_username_sth = $db->prepare('SELECT username FROM site_account WHERE site_id=5 AND user_id=? LIMIT 1');

$date_str = date_with_dow($meeting_row['date']);
echo "<h2>Meeting $date_str</h2>\n";
if (!empty($meeting_row['kattis_contest_id'])) {
	$kattis_contest_name_sth = $db->prepare('SELECT kattis_contest_name FROM kattis_contest WHERE kattis_contest_id=?');
	$kattis_contest_name_sth->execute(array($meeting_row['kattis_contest_id']));
	if ($row = $kattis_contest_name_sth->fetch()) {
		echo "<p>Scraped contest: <a href=\"https://open.kattis.com/contests/{$meeting_row['kattis_contest_id']}\">{$row['kattis_contest_name']} ({$meeting_row['kattis_contest_id']})</a></p>";
	}
	$kattis_solved_sth = $db->prepare('SELECT kattis_username, kattis_problem_id FROM kattis_contest_solved WHERE kattis_contest_id=?');
	$kattis_solved_sth->execute(array($meeting_row['kattis_contest_id']));
	$user_solved = array();
	while ($row = $kattis_solved_sth->fetch()) {
		if (!isset($user_solved[$row['kattis_username']])) {
			$user_solved[$row['kattis_username']] = array();
		}
		$user_solved[$row['kattis_username']][] = $row['kattis_problem_id'];
	}
}
?>

<h3>Alter Meeting</h3>
<form method="post">
<input type="hidden" name="action" value="change_meeting_properties">
<input type="hidden" name="meeting_id" value="<?php echo $meeting_id; ?>">
<label>Meeting date (yyyy-mm-dd): <input type="text" name="date" value="<?php echo $meeting_row['date']; ?>" size="10"></label><br>
<label>Kattis contest ID (optional): <input type="text" name="kattis_contest_id" value="<?php echo $meeting_row['kattis_contest_id']; ?>"></label><br>
<input type="submit" value="Submit Changes">
</form>
<form action="ranking-admin.php" method="post" onsubmit="return confirm('Are you sure?')">
<input type="hidden" name="action" value="delete_meeting">
<input type="hidden" name="meeting_id" value="<?php echo $meeting_id; ?>">
<input type="submit" value="Delete Meeting">
</form>

<h3>Attendance</h3>
<p>Add Bonus can be used to manually credit the user with bonus problems solved. It can also be negative to remove credit.</p>
<form onsubmit="updateMeetingAttendance('meeting-attendance-table', <?php echo $meeting_id; ?>); return false;">
<input type=submit value="Save attendance">
<table border=1 id="meeting-attendance-table">
<tr><th>Name</th><th>Attended</th><th>Add<br>Bonus</th><th>Solved Problems</th></tr>
<?php
foreach ($users as $user) {
	if ($user['deleted']) continue;
	$attended = false;
	$bonus = '';
	if (array_key_exists($user['id'], $attendance)) {
		$attended = true;
		if ($attendance[$user['id']]) $bonus = $attendance[$user['id']];
	}
	$checked = $attended ? ' checked' : '';
	echo "<tr>";
	echo "<td>{$user['first_name']} {$user['last_name']}</td>";
	echo "<td><input type=checkbox name=\"{$user['id']}\"$checked></td>";
	echo "<td><input type=number style=\"width: 30px;\" name=\"{$user['id']}-bonus\" value=\"$bonus\"></td>";
	echo "<td>";
	$get_kattis_username_sth->execute(array($user['id']));
	if ($row = $get_kattis_username_sth->fetch()) {
		if (isset($user_solved[$row['username']])) echo implode(', ', $user_solved[$row['username']]);
	}
	echo "</td>";
	echo "</tr>\n";
}
?>
</table>
<input type=submit value="Save attendance">
</form>
