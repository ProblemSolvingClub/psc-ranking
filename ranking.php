<?php
// Set content type to json.
header('Content-type: application/json');
ini_set('display_errors', 'On');

//$db = new PDO('sqlite:/home/pscadmin/psc-ranking/ranking.sqlite3');
$db = new PDO('sqlite:/Users/jan/dev/psc-ranking/ranking.sqlite3');

// Users with scores.
// [ { id, firstName, lastName, totalSolved, siteSolved: [{siteId: solved}] } ]
$users = array();
$users_sql = 'SELECT id, first_name, last_name FROM user';
// TODO: Get score using sql. Latest solved - Initial solved per site.
$site_solved_sql = 'SELECT solved, created_date FROM site_score WHERE user_id=';
foreach ($db->query($users_sql) as $row) {
    $total_solved = 0;
    // TODO: Get actual score. latest score - beginning score for site
    $site_solved = array(); // { site_id: num_solved_this_semester }
    foreach($db->query($site_solved_sql . $row['id']) as $solved_row) {
        $site_solved
    }

    $users[] = array(
        'id' => $row['id'],
        'firstName' => $row['first_name'],
        'lastName' => $row['last_name'],
        'totalSolved' => $total_solved,
        'siteSolved' => $site_solved
    );
}

// Sites.
$sites = array();
$sites_sql = 'SELECT id, name, profile_url FROM site';
foreach ($db->query($sites_sql) as $row) {
    $sites[] = array(
        'id' => $row['id'],
        'name' => $row['name'],
        'profileUrl' => $row['profile_url']
    );
}

// Tiers.
$tiers = array();
$tiers_sql = 'SELECT id, name, minimum_score FROM tier';
foreach ($db->query($tiers_sql) as $row) {
    $tiers[] = array(
        'id' => $row['id'],
        'name' => $row['name'],
        'minimumScore' => $row['minimum_score']
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
