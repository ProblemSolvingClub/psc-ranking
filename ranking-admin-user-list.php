<?php
if (!defined('IN_PSC_RANKING_ADMIN')) die;
?>
<ul class="inline_list">
<li><b>With selected</b></li>
<?php if (defined('USER_LIST_DELETED')) { ?>
<li>| <a href='javascript:;' onclick="bulkUserModify('user-list-table', 'undelete')">Undelete</a></li>
<?php } else { ?>
<li>| <a href='javascript:;' onclick="bulkUserModify('user-list-table', 'delete')">Delete</a></li>
<li>| <a href='javascript:;' onclick="bulkUserModify('user-list-table', 'make_official')">Make Official</a></li>
<li>| <a href='javascript:;' onclick="bulkUserModify('user-list-table', 'make_unofficial')">Make Unofficial</a></li>
<li>| <a href='javascript:;' onclick="bulkUserModify('user-list-table', 'make_admin')">Make Admin</a></li>
<li>| <a href='javascript:;' onclick="bulkUserModify('user-list-table', 'make_unadmin')">Make Unadmin</a></li>
<?php } ?>
</ul>
<table border="1" id="user-list-table">
<tr><th><input type=checkbox onchange="checkAll('user-list-table', event.target.checked)"></th><th>Name</th><th>UCID</th><th>Attended<br>Meetings</th><th>Bonus<br>Problems</th><th>Website</th><th>Username</th><th>Solved</th><th>Last Updated (UTC)</th></tr>
<?php
$sites_sth = $db->prepare('SELECT site_id, username FROM site_account WHERE user_id=?');
$score_sth = $db->prepare('SELECT solved, created_date FROM site_score WHERE site_id=? AND username=? ORDER BY created_date DESC LIMIT 1');
foreach ($users as $user) {
	if ($user['deleted'] != defined('USER_LIST_DELETED')) continue;
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
	$full_name = htmlspecialchars("{$user['first_name']} {$user['last_name']}");
	echo "<tr><td rowspan=$rowspan>";
	echo "<input type=checkbox name={$user['id']}>";
	echo "</td><td rowspan=$rowspan>";
	echo "<a href='ranking-admin.php?user_id={$user['id']}'>$full_name</a>";
	$flags = array();
	if ($user['admin']) $flags[] = 'Admin';
	if ($user['unofficial']) $flags[] = 'Unofficial';
	if (count($flags) > 0) echo '<br>(' . implode(', ', $flags) . ')';
	echo "</td>\n";
	echo "<td rowspan=$rowspan>{$user['ucid']}</td>";
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
		if (!defined('USER_LIST_DELETED')) {
			echo "<td><a href='javascript:;' onclick=\"deleteAcct({$user['id']}, '" . addslashes($full_name) . "', {$site['id']}, '" . addslashes($site['name']) . "', '" . addslashes($account['username']) . "');\">Delete</a></td>";
		}
		echo "</tr>\n";
	}
	if (empty($accounts)) echo "</tr>\n";
}
?>
</table>
