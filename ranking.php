<?php
// Set content type to json.
header('Content-type: application/json');
ini_set('display_errors', 'On');

$db = new PDO('sqlite:/home/pscadmin/psc-ranking/ranking.sqlite3');

// TODO: Consider putting in a database.
$semesterStartDate = '2016-09-20';

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
$users_sql = 'SELECT id, first_name, last_name FROM user';

// Get all user IDs.
foreach ($db->query($users_sql) as $row) {
    $user_id = $row['id'];
    $total_solved = 0;
    $site_solved = array(); // { site_id: num_solved_this_semester }

    foreach($sites as $site) {
        // Get oldest site_score row for this user and site that is after $semesterStartDate.
        $site_solved_oldest = 0;
        $site_solved_oldest_sql = "
            SELECT solved
            FROM site_score 
            WHERE site_id={$site['id']} AND user_id={$user_id} AND created_date > {$semesterStartDate}
            ORDER BY created_date ASC
            LIMIT 1";

        foreach($db->query($site_solved_oldest_sql) as $site_solved_old_row) {
            $site_solved_oldest = $site_solved_old_row['solved'];
        }

        // Get newest site_score row for this user and site that is after $semesterStartDate.
        $site_solved_newest = 0;
        $site_solved_newest_sql = " 
            SELECT solved
            FROM site_score 
            WHERE site_id={$site['id']} AND user_id={$user_id} AND created_date > {$semesterStartDate}
            ORDER BY created_date DESC
            LIMIT 1";

        foreach($db->query($site_solved_newest_sql) as $site_solved_new_row) {
            $site_solved_newest = $site_solved_new_row['solved'];
        }
        
        // Score is just the difference between latest #solved and oldest #solved.
        $site_score = $site_solved_newest - $site_solved_oldest;
        // Ensure that it doesn't go below 0 e.g. Kattis problem point value drops.
        $site_solved[$site['id']] = $site_score < 0.0 ? 0.0 : $site_score;
        $total_solved += $site_score;
    }

    $users[] = array(
        'id' => $user_id,
        'firstName' => $row['first_name'],
        'lastName' => $row['last_name'],
        'totalSolved' => $total_solved,
        'siteSolved' => $site_solved
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
