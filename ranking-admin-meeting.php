<?php
if (!defined('IN_PSC_RANKING_ADMIN')) die;
$meeting_id = $_GET['meeting_id'];
$meeting_sth = $db->prepare('SELECT date, kattis_contest_id FROM meeting WHERE id=?');
$meeting_sth->execute(array($meeting_id));
$meeting_row = $meeting_sth->fetch();
if ($meeting_row === false) die('Invalid meeting ID');

$attended_user_ids = get_meeting_attendance($meeting_id);

$get_kattis_username_sth = $db->prepare('SELECT username FROM site_account WHERE site_id=5 AND user_id=? LIMIT 1');

echo "<h2>Meeting {$meeting_row['date']}</h2>\n";

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
<form method="post">
<input type="hidden" name="action" value="update_meeting_attendance">
<input type="hidden" name="meeting_id" value="<?php echo $meeting_id; ?>">
<table border=1>
<tr><th>Name</th><th>Attended</th><th>Solved Problems</th></tr>
<?php
foreach ($users as $user) {
	$attended = array_key_exists($user['id'], $attended_user_ids) && $attended_user_ids[$user['id']];
	$checked = $attended ? ' checked' : '';
	echo "<tr>";
	echo "<td>{$user['first_name']} {$user['last_name']}</td>";
	echo "<td><input type=checkbox name=\"{$user['id']}\"$checked></td>";
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
