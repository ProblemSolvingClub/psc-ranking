<?php
if (!defined('IN_PSC_RANKING_ADMIN')) die;
?>
<h3>Log</h3>
<ul>
<?php
$log_sql = 'SELECT user_id, action, target_user_id, target_meeting_id, value, created_date FROM log ORDER BY created_date DESC';
foreach ($db->query($log_sql) as $log_row) {
	switch ($log_row['action']) {
		case LOG_ACTION_LOGIN:
			$text = "logged in";
			break;
		case LOG_ACTION_ADD_ACCOUNT:
			$text = "added account {$log_row['value']} for user {$log_row['target_user_id']}";
			break;
		case LOG_ACTION_ADD_MEETING:
			$text = "created meeting {$log_row['target_meeting_id']} with kattis contest id {$log_row['value']}";
			break;
		case LOG_ACTION_ADD_USER:
			$text = "created user {$log_row['target_user_id']}";
			break;
		case LOG_ACTION_CHANGE_ADMIN:
			$text = "set admin={$log_row['value']} for user {$log_row['target_user_id']}";
			break;
		case LOG_ACTION_CHANGE_MEETING_ATTENDANCE:
			$text = "set attendance={$log_row['value']} for user {$log_row['target_user_id']} in meeting {$log_row['target_meeting_id']}";
			break;
		case LOG_ACTION_CHANGE_UCID:
			$text = "set ucid={$log_row['value']} for user {$log_row['target_user_id']}";
			break;
		case LOG_ACTION_CHANGE_UNOFFICIAL:
			$text = "set unofficial={$log_row['value']} for user {$log_row['target_user_id']}";
			break;
		case LOG_ACTION_DELETE_ACCOUNT:
			$text = "deleted account {$log_row['value']} for user {$log_row['target_user_id']}";
			break;
		default:
			$text = "performed unknown action";
			break;
	}
	$user = $users[$user_ids_to_index[$log_row['user_id']]];
	$user_name = "{$user['first_name']} {$user['last_name']}";
	echo "<li>{$log_row['created_date']} $user_name $text</li>";
}
?>
</ul>