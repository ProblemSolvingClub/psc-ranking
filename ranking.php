<!doctype html>
<html>
<head>
<title>PSC Ranking System</title>
</head>
<body>
<h1>PSC Ranking System</h1>
<table border="1">
<?php
ini_set('display_errors', 'On');
$supportedSites = array(
	'coj' => array(
		'name' => 'Caribbean Online Judge',
		'url' => 'http://coj.uci.cu/user/useraccount.xhtml?username=%s'
	),
	'codechef' => array(
		'name' => 'CodeChef',
		'url' => 'https://www.codechef.com/users/%s'
	),
	'codeforces' => array(
		'name' => 'Codeforces',
		'url' => 'http://www.codeforces.com/profile/%s'
	),
	'icpcarchive' => array(
		'name' => 'ICPC Live Archive',
		'url' => 'https://icpcarchive.ecs.baylor.edu/uhunt/id/%s',
	),
	'kattis' => array(
		'name' => 'Kattis',
		'url' => 'https://open.kattis.com/users/%s'
	),
	'poj' => array(
		'name' => 'Peking Online Judge',
		'url' => 'http://poj.org/userstatus?user_id=%s'
	),
	'spoj' => array(
		'name' => 'Sphere Online Judge',
		'url' => 'http://www.spoj.com/users/%s'
	),
	'uva' => array(
		'name' => 'UVa Online Judge',
		'url' => 'http://uhunt.felix-halim.net/id/%s'
	),
);
$db = new PDO('sqlite:/home/pscadmin/psc-ranking/ranking.sqlite3');

$users = array();
$sql = 'SELECT name, site, username, solved FROM user, user_site WHERE user.id=user_site.user_id';
foreach ($db->query($sql) as $row) {
	if ($row['solved'] !== null) {
		$users[$row['name']][$row['site']] = array(
			'solved' => $row['solved'],
			'username' => $row['username'],
		);
		if (!isset($users[$row['name']]['Total'])) $users[$row['name']]['Total'] = 0;
		$users[$row['name']]['Total'] += $row['solved'];
	}
}
// Sort by reverse order of Total
uasort($users, function($a, $b) {
	$av = $a['Total'];
	$bv = $b['Total'];
	if ($av > $bv) return -1;
	elseif ($av == $bv) return 0;
	else return 1;
});

#CREATE TABLE user_site(user_id integer, site text, username text, solved integer, updated_time integer, primary key(user_id, site));
#CREATE TABLE user(id integer primary key, name text);

// Print header
echo '<tr><th>Name</th><th>Total</th>';
foreach ($supportedSites as $site => $siteObj) {
	// Use small width to compress.
	echo "<th style='width:1px;'>{$siteObj['name']}</th>";
}

// Print rows
echo "</tr>\n";
foreach ($users as $name => $user) {
	echo "<td>$name</td><td>{$user['Total']}</td>";
	foreach ($supportedSites as $site => $siteObj) {
		echo '<td>';
		if (isset($user[$site])) {
			$url = sprintf($siteObj['url'], $user[$site]['username']);
			$solved = $user[$site]['solved'];
			echo "<a target='_blank' href='$url'>$solved</a>";
		}
		echo '</td>';
	}
	echo "</tr>\n";
}
?>
</table>
