<?php
/*******************************************************************************
* my little forum backup script                                                *
* creates a database backup file of version 1.7 for version 2.2                *
* more information on                                                          *
* http://mylittleforum.net/documentation/update_from_version_1.7_to_2.2        *
*******************************************************************************/

$settings['max_queries'] = 500;

class Backup {
	var $start_time;
	var $check_time;
	var $file;
	var $dump = '';
	var $queries = 0;
	var $max_queries = 300;
	var $errors = Array();

	function Backup() {
		@set_time_limit(30);
		$this->start_time = time();
		$this->check_time = $this->start_time;
	}

	function set_max_queries($max_queries) {
		$this->max_queries = $max_queries;
	}

	function set_file($file) {
		$this->file = $file;
	}

	function assign($data) {
		$this->dump .= utf8_encode($data);
		$this->queries++;
		$now = time();
		if (($now-25) >= $this->check_time) {
			$this->check_time = $now;
			@set_time_limit(30);
		}
		if ($this->queries >= $this->max_queries) {
			// buffer:
			$this->save();
			$this->queries = 0;
		}
	}

	function save() {
		if ($this->dump != '') {
			if (empty($this->file)) {
				$this->file = 'mlf_1.7_backup_'.date("YmdHis").'.sql';
			}
			if ($handle = fopen($this->file, 'a+')) {
				#flock($fp, 2);
				fwrite($handle, $this->dump);
				#flock($fp, 3);
				fclose($handle);
				$this->dump = '';
			} else {
				$this->errors[] = 'Could not write backup file!';
			}
		}
		if (empty($errors)) {
			return true;
		} else {
			return false;
		}
	}
}

if (isset($_POST['backup_submit'])) {
	$backup = new Backup;
	$backup->set_max_queries($settings['max_queries']);
	
	if (empty($_POST['db_host']) || empty($_POST['db_name']) || empty($_POST['db_user']) || empty($_POST['db_password']) || empty($_POST['table_prefix_1']) || empty($_POST['table_prefix_2']) || empty($_POST['file'])) {
		$errors[] = 'Not all fields have been filled out.';
	}

	if (empty($errors)) {
		if (!$connid = @mysql_connect($_POST['db_host'], $_POST['db_user'], $_POST['db_password'])) $errors[] = 'Database error: '. mysql_error();
	}

	if (empty($errors)) {
		if (!@mysql_select_db($_POST['db_name'], $connid)) $errors[] = 'Database error: '. mysql_error();
	}

	if (empty($errors)) {
		if (!preg_match('/^[a-zA-Z0-9._\-]+$/', $_POST['file'])) $errors[] = 'Invalid backup file name';
		else if (file_exists($_POST['file'])) $errors[] = 'Backup file already exists';
	}

	if (empty($errors)) {
		$table_prefix = mysql_real_escape_string($_POST['table_prefix_1']);
		$result = @mysql_query("SHOW TABLES", $connid) or die(mysql_error($connid));
		while ($data = mysql_fetch_array($result)) {
			$tables[] = $data[0]; 
		}
		if (!in_array($table_prefix.'entries',$tables) || !in_array($table_prefix.'userdata',$tables) || !in_array($table_prefix.'categories',$tables) || !in_array($table_prefix.'settings',$tables))  {
			$errors[] = 'Database tables not found.';
		}
		mysql_free_result($result);
	}

	if (empty($errors)) {
		// check version:
		$result = @mysql_query("SELECT value FROM ".$table_prefix."settings WHERE name='version' LIMIT 1", $connid) or die(mysql_error($connid));
		if (mysql_num_rows($result) != 1) $errors[] = 'Version could not be determined.';
		else {
			$data = mysql_fetch_array($result);
			$version = $data['value'];
			if (substr($version, 0, 3) != '1.7') $errors[] = 'The backup can only made from version 1.7.*. Installed version is '. htmlspecialchars(stripslashes($version)) .'.';
		}
		mysql_free_result($result); 
	}

	if (empty($errors)) {
		// get quote symbol:
		$result = @mysql_query("SELECT value FROM ".$table_prefix."settings WHERE name='quote_symbol' LIMIT 1", $connid) or die(mysql_error($connid));
		if (mysql_num_rows($result) != 1) $quote_symbol = '»';
		else {
			$data = mysql_fetch_array($result);
			$quote_symbol = $data['value'];
		}
		mysql_free_result($result); 
	}

	if (empty($errors)) {
		$backup->set_file($_POST['file']);
		$mlf2_table_prefix = mysql_real_escape_string($_POST['table_prefix_2']);
		$backup->assign("# Database backup of mlf 1.7 for mlf 2.1 created on ".date("F d, Y, H:i:s")."\n");
		$backup->assign("#\n");
		$backup->assign("# ".$mlf2_table_prefix."categories\n");
		$backup->assign("#\n");
		$backup->assign("TRUNCATE TABLE ".$mlf2_table_prefix."categories;\n");
		$result = @mysql_query("SELECT id, category_order, replace(category,'\\\\','') AS category, replace(description,'\\\\','') AS description, accession FROM ".$table_prefix."categories", $connid) or die(mysql_error($connid));
		while($data = mysql_fetch_array($result)) {
			$data['category'] = mysql_real_escape_string($data['category']);
			$data['description'] = mysql_real_escape_string($data['description']);
			#$data['description'] = str_replace("\r", "\\r", $data['description']);
			#$data['description'] = str_replace("\n",  "\\n", $data['description']);
			$backup->assign("INSERT INTO ".$mlf2_table_prefix."categories VALUES (".$data['id'].", ".$data['category_order'].", '".$data['category']."', '".$data['description']."', ".$data['accession'].");\n");
		}
		mysql_free_result($result);

		$backup->assign("#\n");
		$backup->assign("# ".$mlf2_table_prefix."userdata\n");
		$backup->assign("#\n");
		$backup->assign("TRUNCATE TABLE ".$mlf2_table_prefix."userdata;\n");
		$backup->assign("TRUNCATE TABLE ".$mlf2_table_prefix."userdata_cache;\n");     
		$result = @mysql_query("SELECT user_id, user_type, replace(user_name,'\\\\','') AS user_name, replace(user_real_name,'\\\\','') AS user_real_name, user_pw, user_email, hide_email, user_hp, replace(user_place,'\\\\','') AS user_place, replace(signature,'\\\\','') AS signature, replace(profile,'\\\\','') AS profile, logins, last_login, last_logout, user_ip, registered, new_posting_notify, new_user_notify, user_lock, pwf_code, activate_code FROM ".$table_prefix."userdata ORDER BY user_id ASC", $connid) or die(mysql_error($connid));
		while($data = mysql_fetch_array($result)) {
			$user[$data['user_name']] = $data['user_id'];
			$data['user_name'] = mysql_real_escape_string($data['user_name']);
			$data['user_type'] = mysql_real_escape_string($data['user_type']);
			$data['user_real_name'] = mysql_real_escape_string($data['user_real_name']);
			$data['user_pw'] = mysql_real_escape_string($data['user_pw']);
			$data['user_email'] = mysql_real_escape_string($data['user_email']);
			$data['user_hp'] = mysql_real_escape_string($data['user_hp']);
			$data['user_place'] = mysql_real_escape_string($data['user_place']);
			#$data['signature'] = str_replace("\r", "\\r", $data['signature']);
			#$data['signature'] = str_replace("\n",  "\\n", $data['signature']);
			$data['signature'] = mysql_real_escape_string($data['signature']);
			#$data['profile'] = str_replace("\r", "\\r", $data['profile']);
			#$data['profile'] = str_replace("\n",  "\\n", $data['profile']);
			$data['profile'] = mysql_real_escape_string($data['profile']);
			$data['last_login'] = mysql_real_escape_string($data['last_logout']);
			$data['user_ip'] = mysql_real_escape_string($data['user_ip']);
			$data['registered'] = mysql_real_escape_string($data['registered']);
			$data['pwf_code'] = mysql_real_escape_string($data['pwf_code']);
			$data['activate_code'] = mysql_real_escape_string($data['activate_code']);

			switch($data['user_type']) {
				case 'admin': $data['user_type'] = 2; break;
				case 'mod': $data['user_type'] = 1; break;
				default: $data['user_type'] = 0;
			}

			switch($data['hide_email']) {
				case 0: $data['email_contact'] = 1; break;
				default: $data['email_contact'] = 0;
			}

			$backup->assign("INSERT INTO ".$mlf2_table_prefix."userdata VALUES (".$data['user_id'].", ".$data['user_type'].", '".$data['user_name']."', '".$data['user_real_name']."', 0, '0000-00-00', '".$data['user_pw']."', '".$data['user_email']."', ".$data['email_contact'].", '".$data['user_hp']."', '".$data['user_place']."', '".$data['signature']."', '".$data['profile']."', ".$data['logins'].", '".$data['last_login']."', '".$data['last_logout']."', '".$data['user_ip']."', '".$data['registered']."', NULL, 0, 0, 1, 0, 0, ".$data['new_posting_notify'].", ".$data['new_user_notify'].", ".$data['user_lock'].", '', '".$data['pwf_code']."', '".$data['activate_code']."', '', '', 0, '', '');\n");
		}
		mysql_free_result($result);

		$backup->assign("#\n");
		$backup->assign("# ".$mlf2_table_prefix."entries\n");
		$backup->assign("#\n");
		$backup->assign("TRUNCATE TABLE ".$mlf2_table_prefix."entries;\n");
		$backup->assign("TRUNCATE TABLE ".$mlf2_table_prefix."entries_cache;\n");

		list($rows) = @mysql_fetch_row(@mysql_query("SELECT COUNT(*) FROM ".$table_prefix."entries", $connid));
		$cycles = ceil($rows/$settings['max_queries']);

		for ($c = 1; $c <= $cycles; ++$c) {
			$ul = ($c-1) * $settings['max_queries'];
			$result = @mysql_query("SELECT id, pid, tid, uniqid, time, last_answer, edited, edited_by, user_id, replace(name,'\\\\','') AS name, replace(subject,'\\\\','') AS subject, category, email, hp, replace(place,'\\\\','') AS place, ip, replace(text,'\\\\','') AS text, show_signature, email_notify, marked, locked, fixed, views FROM ".$table_prefix."entries ORDER BY id ASC LIMIT ".$ul.", ".$settings['max_queries'], $connid) or die(mysql_error($connid));
			while ($data = mysql_fetch_array($result)) {
				$data['uniqid'] = mysql_real_escape_string($data['uniqid']);
				$data['time'] = mysql_real_escape_string($data['time']);
				$data['last_answer'] = mysql_real_escape_string($data['last_answer']);
				$data['edited'] = mysql_real_escape_string($data['edited']);
				#if (is_null($data['edited_by'])) $data['edited_by'] = 'NULL'; else $data['edited_by'] = intval($data['edited_by']);
				$data['name'] = mysql_real_escape_string($data['name']);
				$data['subject'] = mysql_real_escape_string($data['subject']);
				$data['email'] = mysql_real_escape_string($data['email']);
				$data['place'] = mysql_real_escape_string($data['place']);
				$data['ip'] = mysql_real_escape_string($data['ip']);
				$data['text'] = str_replace(utf8_decode($quote_symbol), '>', $data['text']);
				$data['text'] = str_replace('img/uploaded', 'images/uploaded', $data['text']);
				$data['text'] = str_replace('forum_entry.php', 'index.php', $data['text']);
				#$data['text'] = str_replace("\r", "\\r", $data['text']);
				#$data['text'] = str_replace("\n",  "\\n", $data['text']);
				$data['text'] = mysql_real_escape_string($data['text']);

				if (trim($data['edited_by']) != '') {
					if(isset($user[$data['edited_by']])) {
						$data['edited_by_id'] = $user[$data['edited_by']];
					}
					else $data['edited_by_id'] = 'NULL';
				}
				else $data['edited_by_id'] = 'NULL';

				if ($data['user_id'] > 0) $data['name'] = '';
				$backup->assign("INSERT INTO ".$mlf2_table_prefix."entries VALUES (".$data['id'].", ".$data['pid'].", ".$data['tid'].", '".$data['uniqid']."', '".$data['time']."', '".$data['last_answer']."', '".$data['edited']."', ".$data['edited_by_id'].", ".$data['user_id'].", '".$data['name']."', '".$data['subject']."', ".$data['category'].", '".$data['email']."', '".$data['hp']."', '".$data['place']."', '".$data['ip']."', '".$data['text']."', '', ".$data['show_signature'].", ".$data['email_notify'].", ".$data['marked'].", ".$data['locked'].", ".$data['fixed'].", ".$data['views'].", 0, 0, '');\n");
			}
			mysql_free_result($result);
		}

		if ($backup->save()) {
			#echo $backup->time . '<br />' .time();
			$filesize = number_format(filesize($backup->file)/1048576, 2);
			$elapse_time = time() - $backup->start_time; 
			$action = 'done';
		} else {
			$errors[] = $backup->errors;
		}

		/*
		$len = strlen($dump);
		$filename = 'mlf_1.7_backup_'.date("YmdHis").'.sql';
		header("Content-Type: text/plain; charset=utf-8");
		header("Content-Disposition: attachment; filename=\"".$filename);
		header("Accept-Ranges: bytes");
		header("Content-Length: ".$len);
		echo $dump;
		exit;
		*/
	}
}

if(empty($action)) $action = 'main';

header('Content-Type: text/html; charset=utf-8');

?><!DOCTYPE html>
<html lang="en">
 <head>
  <meta charset="utf-8">
  <title>my little forum 1.7 backup</title>
  <style type="text/css">
<!--
body              { color:#000; background:#fff; margin:0; padding:0; font-family: verdana, arial, sans-serif; font-size:100.1%; }
h1                { font-size:1.25em; }
h2                { font-size:16px; margin:0; padding:3px 0 3px 0; }
p,ul              { font-size:0.82em; line-height:1.45em; max-width:700px; }
header            { margin:0; padding:0 20px 0 20px; color:#000000; background:#d2ddea; border-bottom: 1px solid #bacbdf; }
header h1         { font-size:1.75em; line-height:2.2em; margin:0; padding:0; color:#000080; }
#content          { padding:20px; }
table.admintab    { border: 1px solid #bacbdf; }
td.admintab-t     { vertical-align: top; font-family: verdana, arial, sans-serif; background: #d2ddea; font-size:1em; font-weight:bold; }
td.admintab-l     { width: 50%; vertical-align: top; font-family: verdana, arial, sans-serif; font-size: 13px; background: #e2eaf3; }
td.admintab-r     { width: 50%; vertical-align: top; font-family: verdana, arial, sans-serif; font-size: 13px; background: #f5f5f5; }
.caution          { color: red; font-weight: bold; }
.small            { font-size: 11px; }
a:link            { color: #0000cc; text-decoration: none; }
a:visited         { color: #0000cc; text-decoration: none; }
a:hover           { color: #0000ff; text-decoration: underline; }
a:active          { color: #ff0000; text-decoration: none; }
-->
  </style>
 </head>
 <body>
  <header id="top">
   <h1>my little forum 1.7 backup</h1>
  </header>

  <div id="content">

<?php switch($action):
case 'done': ?>
   <p><strong>Backup file created!</strong></p>
   <p><a href="<?php echo $backup->file; ?>"><?php echo $backup->file; ?></a> <span class="small">(<?php echo $filesize; ?></a> MB)</span></p>
   <p class="small">Elapse time: <?php if($elapse_time<1) echo 'less than 1 second'; else echo $elapse_time . ' seconds'; ?></p>
<?php break; ?>

<?php default: ?>

   <p>By submitting this form the specified backup file will be created in 
<?php echo dirname($_SERVER['PHP_SELF']); ?> so make sure that this direcory is 
writable.<br />
You can import the backup file into version 2.1 with a tool like phpMyAdmin or the 
backup function of version 2.1 (therefor copy the file into the directory "backup").</p>

<?php if(ini_get('safe_mode')): ?>
   <p><strong>Warning:</strong> As "safe mode" is activated on this server the script running time cannot be extended! This may cause an error and an uncomplete backup file if there's a large amount of entries.</p>
<?php endif; ?>

<?php

if(isset($errors))
    {
     ?><p class="caution">Error!</p><ul><?php foreach($errors as $error) { ?><li><?php echo $error; ?></li><?php } ?></ul><?php
    }

?>
   <form action="<?php echo basename($_SERVER['PHP_SELF']); ?>" method="post">
    <table class="admintab" border="0" cellpadding="5" cellspacing="1">
     <tr>
      <td class="admintab-t" colspan="2">Settings of the old forum (1.7*)</td>
     </tr>
     <tr>
      <td class="admintab-l"><label for="db_host"><strong>Database host</strong></label><br /><span class="small">host name, probably "localhost"</span></td>
      <td class="admintab-r"><input id="db_host" type="text" name="db_host" value="<?php if (isset($_POST['db_host'])) echo htmlspecialchars(stripslashes($_POST['db_host'])); else echo 'localhost'; ?>" size="40" /></td>
     </tr>
     <tr>
      <td class="admintab-l"><label for="db_name"><strong>Database name</strong></label><br /><span class="small">Name of the database</span></td>
      <td class="admintab-r"><input id="db_name" type="text" name="db_name" value="<?php if (isset($_POST['db_name'])) echo htmlspecialchars(stripslashes($_POST['db_name'])); else echo ''; ?>" size="40" /></td>
     </tr>
     <tr>
      <td class="admintab-l"><label for="db_user"><strong>Database user</strong></label><br /><span class="small">Username to access the database</span></td>
      <td class="admintab-r"><input id="db_user" type="text" name="db_user" value="<?php if (isset($_POST['db_user'])) echo htmlspecialchars(stripslashes($_POST['db_user'])); else echo ''; ?>" size="40" /></td>
     </tr>
     <tr>
      <td class="admintab-l"><label for="db_password"><strong>Database password</strong></label><br />
      <span class="small">Password to access the database</span></td>
      <td class="admintab-r"><input id="db_password" type="password" name="db_password" size="40" /></td>
     </tr>
     <tr>
      <td class="admintab-l"><label for="table_prefix_1"><strong>Database Table prefix</strong></label><br /><span class="small">Table prefix, probably "forum_"</span></td>
      <td class="admintab-r"><input id="table_prefix_1" type="text" name="table_prefix_1" value="<?php if (isset($_POST['table_prefix_1'])) echo htmlspecialchars(stripslashes($_POST['table_prefix_1'])); else echo 'forum_'; ?>" size="40" /></td>
     </tr>
     <tr>
      <td class="admintab-t" colspan="2">Settings of the new forum (2.1)</td>
     </tr>
     <tr>
      <td class="admintab-l"><label for="table_prefix_2"><strong>Database Table prefix</strong></label><br /><span class="small">Table prefix, probably "mlf2_"</span></td>
      <td class="admintab-r"><input id="table_prefix_2" type="text" name="table_prefix_2" value="<?php if (isset($_POST['table_prefix_2'])) echo htmlspecialchars(stripslashes($_POST['table_prefix_2'])); else echo 'mlf2_'; ?>" size="40" /></td>
     </tr>
     <tr>
      <td class="admintab-t" colspan="2">Backup file</td>
     </tr>
     <tr>
      <td class="admintab-l"><label for="file"><strong>Filename</strong></label><br /><span class="small">Name of the backup file</span></td>
      <td class="admintab-r"><input id="file" type="text" name="file" value="<?php if (isset($_POST['file'])) echo stripslashes($_POST['file']); else echo 'mlf_1.7_backup_'.date("YmdHis").'.sql'; ?>" size="40" /></td>
     </tr>
     <tr>
      <td class="admintab-l">&nbsp;</td>
      <td class="admintab-r"><input type="submit" name="backup_submit" value="OK - Create backup file" /></td>
     </tr>
    </table>
   </form>

<?php endswitch; ?>

  </div>
 </body>
</html>
