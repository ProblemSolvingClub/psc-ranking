<?php
if (!defined('IN_PSC_RANKING_ADMIN')) die;
?>
<table border="1">
<tr><th>Date</th><th>Kattis Contest ID</th><th>Last Scraped</th></tr>
<?php
$dateRestr = defined('ALL_MEETING_LIST') ? '' : " AND meeting.date>='$semesterStartDate'";
$meetings = $db->query("SELECT id, date, meeting.kattis_contest_id, kattis_contest_name, kattis_contest.created_date FROM meeting LEFT JOIN kattis_contest ON meeting.kattis_contest_id=kattis_contest.kattis_contest_id WHERE NOT meeting.deleted$dateRestr ORDER BY date DESC")->fetchAll();
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
