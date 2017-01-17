<?php
if (!defined('IN_PSC_RANKING_ADMIN')) die;
$user_id = $_GET['user_id'];
$user_sth = $db->prepare('SELECT id, first_name, last_name, ucid, unofficial, admin FROM user WHERE id=?');
$user_sth->execute(array($user_id));
$user_row = $user_sth->fetch();
if ($user_row === false) die('Invalid user ID');

echo "<h2>User: {$user_row['first_name']} {$user_row['last_name']}</h2>\n";
?>
<h3>Attended Meetings</h3>
<ol>
<?php

$attended_meetings_sth = $db->prepare('SELECT id, date FROM meeting m WHERE (SELECT attended FROM meeting_attended ma WHERE m.id=ma.meeting_id AND ma.user_id=? ORDER BY created_date DESC LIMIT 1)=1 ORDER BY date DESC');
$attended_meetings_sth->execute(array($user_id));
while ($row = $attended_meetings_sth->fetch()) {
	echo "<li><a href='ranking-admin.php?meeting_id={$row['id']}'>{$row['date']}</a></li>\n";
}
?>
</ol>
<?php
$sites_sth = $db->prepare('SELECT s.id, s.name, sa.username FROM site s, site_account sa WHERE s.id=sa.site_id AND sa.user_id=?');
$sites_sth->execute(array($user_id));
$sites_rows = $sites_sth->fetchAll();
$site_scores_sth = $db->prepare('SELECT created_date, solved FROM site_score WHERE site_id=? AND username=? ORDER BY created_date');

foreach ($sites_rows as $site_row) {
	$site_scores_sth->execute(array($site_row['id'], $site_row['username']));
	$scores = array();
	while ($score_row = $site_scores_sth->fetch()) {
		if (count($scores) > 0 && $scores[count($scores)-1]['solved'] == $score_row['solved']) {
			$scores[count($scores)-1]['end_date'] = $score_row['created_date'];
		} else {
			$gained = 0;
			if (count($scores) > 0) {
				$gained = max(0.0, $score_row['solved'] - $scores[count($scores)-1]['solved']);

                // Special handling for Kattis (divide by 2 and round for now).
                if ($site_row['id'] == 5) {
                    $gained = round(0.5 * $gained);
                }
			}
			$scores[] = array(
				'start_date' => $score_row['created_date'],
				'end_date' => $score_row['created_date'],
				'solved' => $score_row['solved'],
				'gained' => $gained,
			);
		}
	}
	echo "<h3>Account: {$site_row['name']} {$site_row['username']}</h3>";
	echo "<ul>";
	foreach (array_reverse($scores) as $score) {
		echo "<li>";
		echo substr($score['start_date'], 0, 10);
		echo ' to ';
		echo substr($score['end_date'], 0, 10);
		echo ": {$score['solved']}";
		if ($score['gained'] > 0) {
			echo " (solved {$score['gained']})";
		}
		echo "</li>";
	}
	echo "</ul>";
}
