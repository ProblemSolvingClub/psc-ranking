<?php
// Set content type to json.
header('Content-type: application/json');
ini_set('display_errors', 'On');

$db = new PDO('sqlite:/home/pscadmin/psc-ranking/ranking.sqlite3');

$semesterStartDate = $db->query('SELECT value FROM setting WHERE name=\'semester_start_date\'')->fetch()[0];

// Sites.
$sites = array();
$sites_sql = 'SELECT id, name, profile_url FROM site';
foreach ($db->query($sites_sql) as $row) {
    $sites[] = array(
        'id' => (int)$row['id'],
        'name' => $row['name'],
        'profileUrl' => $row['profile_url']
    );
}

// Tiers.
$tiers = array();
$tiers_sql = 'SELECT id, name, minimum_score FROM tier';
foreach ($db->query($tiers_sql) as $row) {
    $tiers[] = array(
        'id' => (int)$row['id'],
        'name' => $row['name'],
        'minimumScore' => (int)$row['minimum_score']
    );
}

// Users with scores.
// [ { id, firstName, lastName, totalSolved, siteSolved: [{siteId: solved}] } ]
$users = array();
$users_sql = "SELECT id, first_name, last_name, (SELECT COUNT(*) FROM meeting m, meeting_attended a WHERE (NOT m.deleted) AND m.id=a.meeting_id AND a.user_id=user.id AND m.date>='{$semesterStartDate}') AS meeting_count, (SELECT COUNT(*) FROM site_account sa, kattis_contest_solved kcs, meeting m WHERE sa.user_id=user.id AND sa.site_id=5 AND sa.username=kcs.kattis_username AND kcs.kattis_contest_id=m.kattis_contest_id AND m.date>='{$semesterStartDate}' AND (NOT m.deleted)) AS bonus_count FROM user WHERE NOT (deleted OR unofficial)";
$site_solved_all_sth = $db->prepare("SELECT solved FROM site_score WHERE site_id=? AND username=? AND created_date > '{$semesterStartDate}' ORDER BY created_date ASC");
$site_solved_before_sth = $db->prepare("SELECT solved FROM site_score WHERE site_id=? AND username=? AND created_date < '{$semesterStartDate}' ORDER BY created_date DESC LIMIT 1");
$site_solved_oldest_sth = $db->prepare("SELECT solved FROM site_score WHERE site_id=? AND username=? ORDER BY created_date ASC LIMIT 1");

// Get all user IDs.
foreach ($db->query($users_sql) as $row) {
    $user_id = $row['id'];

	// 3 points for attendance and 2 extra points for bonus problem
    $total_solved = $row['meeting_count']*3 + $row['bonus_count']*2;
    $site_solved = array(); // { site_id: num_solved_this_semester }

    foreach($sites as $site) {
        // Get all usernames for the user and site.
        $usernames_sql = "SELECT username FROM site_account where user_id={$user_id} and site_id={$site['id']}";
        foreach ($db->query($usernames_sql) as $username_row) {
            $username = $username_row['username'];

            // Get newest site_score row for this user and site that is before $semesterStartDate.
            $last_solved = 0;
            $site_solved_before_sth->execute(array($site['id'], $username));
            if ($site_solved_before_row = $site_solved_before_sth->fetch()) {
                $last_solved = $site_solved_before_row['solved'];
            } else {
                // If we never scraped user before semesterStartDate, we will allow them at most 5 more than the lowest score we have.
                $site_solved_oldest_sth->execute(array($site['id'], $username));
                if ($site_solved_oldest_row = $site_solved_oldest_sth->fetch()) {
                    $last_solved = max(0, $site_solved_oldest_row['solved'] - 5);
                }
            }

            // Get all scores after $semesterStartDate.
            $site_solved_all_sth->execute(array($site['id'], $username));
            foreach ($site_solved_all_sth as $solved_row) {
                // Find number solved in the interval.
                $solved = max(0, $solved_row['solved'] - $last_solved);

                // Special handling for Kattis (divide by 2 and round for now).
                if ($site['id'] == 5) {
                    $solved = round(0.5 * $solved);
                }

                if (!isset($site_solved[$site['id']])) {
                    $site_solved[$site['id']] = 0;
                }
                $site_solved[$site['id']] += $solved;
                $total_solved += $solved;
                $last_solved = $solved_row['solved'];
            }
        }
    }

    $users[] = array(
        'id' => $user_id,
        'firstName' => $row['first_name'],
        'lastName' => $row['last_name'],
        'totalSolved' => $total_solved,
        'siteSolved' => $site_solved,
        'attendedMeetings' => $row['meeting_count'],
        'bonusProblems' => $row['bonus_count'],
    );
}

// Final json object.
$json_info = array(
    'users' => $users,
    'sites' => $sites,
    'tiers' => $tiers
);

echo json_encode($json_info);
?>
