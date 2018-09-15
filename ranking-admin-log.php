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
		case LOG_ACTION_CHANGE_SEMESTER_START_DATE:
			$text = "changed semester start date to {$log_row['value']}";
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
		case LOG_ACTION_CHANGE_DELETED:
			$acttext = $log_row['value'] ? 'deleted' : 'undeleted';
			$text = "$acttext user {$log_row['target_user_id']}";
			break;
		case LOG_ACTION_DELETE_MEETING:
			$text = "deleted meeting {$log_row['target_meeting_id']}";
			break;
		case LOG_ACTION_ALTER_MEETING:
			$text = "altered meeting {$log_row['target_meeting_id']} with properties " .
				htmlspecialchars($log_row['value']);
			break;
		case LOG_ACTION_CHANGE_FIRST_LAST_NAME:
			$text = "changed user {$log_row['target_user_id']} first/last name to " .
				htmlspecialchars($log_row['value']);
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
