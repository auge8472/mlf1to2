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
		if (!$connid = @mysqli_connect($_POST['db_host'], $_POST['db_user'], $_POST['db_password'], $_POST['db_name'])) $errors[] = 'Database error: '. mysqli_connect_error();
	}

	if (empty($errors)) {
		if (!preg_match('/^[a-zA-Z0-9._\-]+$/', $_POST['file'])) $errors[] = 'Invalid backup file name';
		else if (file_exists($_POST['file'])) $errors[] = 'Backup file already exists';
	}

	if (empty($errors)) {
		$table_prefix = mysqli_real_escape_string($connid, $_POST['table_prefix_1']);
		$result = @mysqli_query($connid, "SHOW TABLES") or die(mysqli_error($connid));
		while ($data = mysqli_fetch_assoc($result)) {
			$tables[] = $data[0]; 
		}
		if (!in_array($table_prefix.'entries',$tables) || !in_array($table_prefix.'userdata',$tables) || !in_array($table_prefix.'categories',$tables) || !in_array($table_prefix.'settings',$tables))  {
			$errors[] = 'Database tables not found.';
		}
		mysqli_free_result($result);
	}

	if (empty($errors)) {
		// check version:
		$result = @mysqli_query($connid, "SELECT value FROM ".$table_prefix."settings WHERE name='version' LIMIT 1") or die(mysqli_error($connid));
		if (mysqli_num_rows($result) != 1) $errors[] = 'Version could not be determined.';
		else {
			$data = mysqli_fetch_assoc($result);
			$version = $data['value'];
			if (substr($version, 0, 3) != '1.7') $errors[] = 'The backup can only made from version 1.7.*. Installed version is '. htmlspecialchars($version) .'.';
		}
		mysqli_free_result($result);
	}

	if (empty($errors)) {
		// get quote symbol:
		$result = @mysqli_query($connid, "SELECT value FROM ".$table_prefix."settings WHERE name='quote_symbol' LIMIT 1") or die(mysqli_error($connid));
		if (mysqli_num_rows($result) != 1) $quote_symbol = '»';
		else {
			$data = mysqli_fetch_assoc($result);
			$quote_symbol = $data['value'];
		}
		mysqli_free_result($result);
	}

	if (empty($errors)) {
		$backup->set_file($_POST['file']);
		$mlf2_table_prefix = mysqli_real_escape_string($connid, $_POST['table_prefix_2']);
		$backup->assign("# Database backup of mlf 1.7 for mlf 2.1 created on ".date("F d, Y, H:i:s")."\n");
		$backup->assign("#\n");
		$backup->assign("# ".$mlf2_table_prefix."categories\n");
		$backup->assign("#\n");
		$backup->assign("TRUNCATE TABLE ".$mlf2_table_prefix."categories;\n");
		$result = @mysqli_query($connid, "SELECT id, category_order, replace(category,'\\\\','') AS category, replace(description,'\\\\','') AS description, accession FROM ".$table_prefix."categories") or die(mysqli_error($connid));
		while ($data = mysqli_fetch_assoc($result)) {
			$data['category'] = mysqli_real_escape_string($connid, $data['category']);
			$data['description'] = mysqli_real_escape_string($connid, $data['description']);
			$backup->assign("INSERT INTO ".$mlf2_table_prefix."categories VALUES (".$data['id'].", ".$data['category_order'].", '".$data['category']."', '".$data['description']."', ".$data['accession'].");\n");
		}
		mysqli_free_result($result);

		$backup->assign("#\n");
		$backup->assign("# ".$mlf2_table_prefix."userdata\n");
		$backup->assign("#\n");
		$backup->assign("TRUNCATE TABLE ".$mlf2_table_prefix."userdata;\n");
		$backup->assign("TRUNCATE TABLE ".$mlf2_table_prefix."userdata_cache;\n");
		$result = @mysqli_query($connid, "SELECT user_id, user_type, replace(user_name,'\\\\','') AS user_name, replace(user_real_name,'\\\\','') AS user_real_name, user_pw, user_email, hide_email, user_hp, replace(user_place,'\\\\','') AS user_place, replace(signature,'\\\\','') AS signature, replace(profile,'\\\\','') AS profile, logins, last_login, last_logout, user_ip, registered, new_posting_notify, new_user_notify, user_lock, pwf_code, activate_code FROM ".$table_prefix."userdata ORDER BY user_id ASC") or die(mysqli_error($connid));
		while ($data = mysqli_fetch_assoc($result)) {
			$user[$data['user_name']] = $data['user_id'];
			$data['user_name'] = mysqli_real_escape_string($connid, $data['user_name']);
			$data['user_type'] = mysqli_real_escape_string($connid, $data['user_type']);
			$data['user_real_name'] = mysqli_real_escape_string($connid, $data['user_real_name']);
			$data['user_pw'] = mysqli_real_escape_string($connid, $data['user_pw']);
			$data['user_email'] = mysqli_real_escape_string($connid, $data['user_email']);
			$data['user_hp'] = mysqli_real_escape_string($connid, $data['user_hp']);
			$data['user_place'] = mysqli_real_escape_string($connid, $data['user_place']);
			$data['signature'] = mysqli_real_escape_string($connid, $data['signature']);
			$data['profile'] = mysqli_real_escape_string($connid, $data['profile']);
			$data['last_login'] = mysqli_real_escape_string($connid, $data['last_logout']);
			$data['user_ip'] = mysqli_real_escape_string($connid, $data['user_ip']);
			$data['registered'] = mysqli_real_escape_string($connid, $data['registered']);
			$data['pwf_code'] = mysqli_real_escape_string($connid, $data['pwf_code']);
			$data['activate_code'] = mysqli_real_escape_string($connid, $data['activate_code']);

			switch ($data['user_type']) {
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
		mysqli_free_result($result);

		$backup->assign("#\n");
		$backup->assign("# ".$mlf2_table_prefix."entries\n");
		$backup->assign("#\n");
		$backup->assign("TRUNCATE TABLE ".$mlf2_table_prefix."entries;\n");
		$backup->assign("TRUNCATE TABLE ".$mlf2_table_prefix."entries_cache;\n");

		list($rows) = @mysqli_fetch_row(@mysqli_query($connid, "SELECT COUNT(*) FROM ".$table_prefix."entries"));
		$cycles = ceil($rows/$settings['max_queries']);

		for ($c = 1; $c <= $cycles; ++$c) {
			$ul = ($c-1) * $settings['max_queries'];
			$result = @mysqli_query($connid, "SELECT id, pid, tid, uniqid, time, last_answer, edited, (SELECT user_id FROM ".$table_prefix."userdata WHERE ".$table_prefix."userdata.user_id = edited_by) AS edited_by, user_id, replace(name,'\\\\','') AS name, replace(subject,'\\\\','') AS subject, category, email, hp, replace(place,'\\\\','') AS place, ip, replace(text,'\\\\','') AS text, show_signature, email_notify, marked, locked, fixed, views FROM ".$table_prefix."entries ORDER BY id ASC LIMIT ".$ul.", ".$settings['max_queries']) or die(mysqli_error($connid));
			while ($data = mysqli_fetch_assoc($result)) {
				$data['uniqid'] = mysqli_real_escape_string($connid, $data['uniqid']);
				$data['time'] = mysqli_real_escape_string($connid, $data['time']);
				$data['last_answer'] = mysqli_real_escape_string($connid, $data['last_answer']);
				$data['edited'] = mysqli_real_escape_string($connid, $data['edited']);
				$data['edited_by'] = (is_null($data['edited_by'])) ? 'NULL' : intval($data['edited_by']);
				$data['name'] = mysqli_real_escape_string($connid, $data['name']);
				$data['subject'] = mysqli_real_escape_string($connid, $data['subject']);
				$data['email'] = mysqli_real_escape_string($connid, $data['email']);
				$data['place'] = mysqli_real_escape_string($connid, $data['place']);
				$data['ip'] = mysqli_real_escape_string($connid, $data['ip']);
				$data['text'] = str_replace(utf8_decode($quote_symbol), '>', $data['text']);
				$data['text'] = str_replace('img/uploaded', 'images/uploaded', $data['text']);
				$data['text'] = str_replace('forum_entry.php', 'index.php', $data['text']);
				$data['text'] = mysqli_real_escape_string($connid, $data['text']);

				if (trim($data['edited_by']) != '') {
					if (isset($user[$data['edited_by']])) {
						$data['edited_by_id'] = $user[$data['edited_by']];
					}
					else $data['edited_by_id'] = 'NULL';
				}
				else $data['edited_by_id'] = 'NULL';

				if ($data['user_id'] > 0) $data['name'] = '';
				$backup->assign("INSERT INTO ".$mlf2_table_prefix."entries VALUES (".$data['id'].", ".$data['pid'].", ".$data['tid'].", '".$data['uniqid']."', '".$data['time']."', '".$data['last_answer']."', '".$data['edited']."', ".$data['edited_by_id'].", ".$data['user_id'].", '".$data['name']."', '".$data['subject']."', ".$data['category'].", '".$data['email']."', '".$data['hp']."', '".$data['place']."', '".$data['ip']."', '".$data['text']."', '', ".$data['show_signature'].", ".$data['email_notify'].", ".$data['marked'].", ".$data['locked'].", ".$data['fixed'].", ".$data['views'].", 0, 0, '');\n");
			}
			mysqli_free_result($result);
		}

		if ($backup->save()) {
			$filesize = number_format(filesize($backup->file)/1048576, 2);
			$el_time = time() - $backup->start_time;
			$el_out = ($el_time < 1) ? 'less than 1 second' : $el_time . ' seconds';
			$action = 'done';
		} else {
			$errors[] = $backup->errors;
		}
	}
}

if (empty($action)) $action = 'main';

header('Content-Type: text/html; charset=utf-8');

?><!DOCTYPE html>
<html lang="en">
 <head>
  <meta charset="utf-8">
  <title>my little forum 1.7 backup</title>
  <style type="text/css">
html, body       { color: #000; background: #fff; margin: 0; padding: 0; font-family: sans-serif; }
header           { margin: 0; padding: 0.25rem 1rem; color:#000; background:#d2ddea; border-bottom: 1px solid #bacbdf; }
header h1        { font-size: 1.5em; margin: 0; padding: 0; color: #008; }
main             { padding: 1rem; }
main section:not(:last-child) { margin: 0 0 1rem 0; }
main h2          { font-size: 1.25em; margin: 0; padding: 3px 0; }
p, ul, form      { font-size: 1em; margin: 0.5rem 0 0 0; line-height:1.45em; }
fieldset:not(:first-child) { margin: 0.5rem 0 0 0; }
legend           { font-weight: bold; }
form div:not(:last-child) { padding-bottom: 0.5rem; border-bottom: 1px solid #bacbdf; }
label            { font-weight: bold; cursor: pointer; }
#caution         { background: #ffc; border: 3px solid #a00; padding: 0.5rem; }
#caution h2      { color: a00; }
#caution ul      { padding: 0 0 0 1rem; }
a:link, a:visited{ color: #00c; text-decoration: none; }
a:focus, a:hover { color: #00f; text-decoration: underline; }
a:active         { color: #f00; text-decoration: none; }
  </style>
 </head>
 <body>
  <header id="top">
   <h1>my little forum 1.7 backup</h1>
  </header>

  <main id="content">

<?php switch($action):
case 'done': ?>
   <section>
    <h2>Backup file created!</h2>
    <p><a href="<?php echo $backup->file; ?>"><?php echo $backup->file; ?></a> <span class="small">(<?php echo $filesize; ?></a> MB)</span></p>
    <p class="small">Elapse time: <?php echo $el_out; ?></p>
   </section>
<?php break; ?>

<?php default: ?>

<?php if (isset($errors)): ?>
   <section id="caution">
    <h2>Error!</h2>
    <ul>
<?php foreach ($errors as $error): ?>
     <li><?php echo $error; ?></li>
<?php endforeach; ?>
    </ul>
   </section>
<?php endif; ?>
   <section>
    <h2>A few notices</h2>
    <p>By submitting this form the specified backup file will be created in <kbd><?php echo dirname($_SERVER['PHP_SELF']); ?></kbd> so make sure that this direcory is writable.<br>You can import the backup file into version 2.1 with a tool like phpMyAdmin or the backup function of version 2.1 (therefor copy the file into the directory "backup").</p>
<?php if (ini_get('safe_mode')): ?>
    <p><strong>Warning:</strong> As "safe mode" is activated on this server the script running time cannot be extended! This may cause an error and an uncomplete backup file if there's a large amount of entries.</p>
<?php endif; ?>
   </section>
   <section>
    <h2>Preparations for running the script</h2>
    <form action="<?php echo basename($_SERVER['PHP_SELF']); ?>" method="post">
     <fieldset>
      <legend>Settings of the old forum (1.7*)</legend>
      <div>
       <p><label for="db_host">Database host</label><br><span>host name, probably "localhost"</span></p>
       <p><input id="db_host" type="text" name="db_host" value="<?php if (isset($_POST['db_host'])) echo htmlspecialchars($_POST['db_host']); ?>" size="40" placeholder="localhost"></p>
      </div>
      <div>
       <p><label for="db_name">Database name</label><br><span>Name of the database</span></p>
       <p><input id="db_name" type="text" name="db_name" value="<?php if (isset($_POST['db_name'])) echo htmlspecialchars($_POST['db_name']); ?>" size="40"></p>
      </div>
      <div>
       <p><label for="db_user">Database user</label><br><span>Username to access the database</span></p>
       <p><input id="db_user" type="text" name="db_user" value="<?php if (isset($_POST['db_user'])) echo htmlspecialchars(stripslashes($_POST['db_user'])); ?>" size="40"></p>
      </div>
      <div>
       <p><label for="db_password">Database password</label><br><span>Password to access the database</span></p>
       <p><input id="db_password" type="password" name="db_password" size="40"></p>
      </div>
      <div>
       <p><label for="table_prefix_1">Database Table prefix</label><br><span>Table prefix, probably "forum_"</span></p>
       <p><input id="table_prefix_1" type="text" name="table_prefix_1" value="<?php if (isset($_POST['table_prefix_1'])) echo htmlspecialchars($_POST['table_prefix_1']); ?>" size="40" placeholder="forum_"></p>
      </div>
     </fieldset>
     <fieldset>
      <legend>Settings of the new forum (>= 2.1)</legend>
      <div>
       <p><label for="table_prefix_2">Database Table prefix</label><br><span>Table prefix, probably "mlf2_"</span></p>
       <p><input id="table_prefix_2" type="text" name="table_prefix_2" value="<?php if (isset($_POST['table_prefix_2'])) echo htmlspecialchars($_POST['table_prefix_2']); ?>" size="40" placeholder="mlf2_"></p>
      </div>
     </fieldset>
     <fieldset>
      <legend>Backup file</legend>
      <div>
       <p><label for="file">Filename</label><br><span>Name of the backup file, <em>do not use blanks in file name!</em></span></p>
       <p><input id="file" type="text" name="file" value="<?php echo (isset($_POST['file'])) ? htmlspecialchars($_POST['file']) : 'mlf_1.7_backup_'.date("YmdHis").'.sql'; ?>" size="40"></p>
      </div>
     </fieldset>
     <p><button name="backup_submit">OK - Create backup file</button></p>
    </form>
   </section>

<?php endswitch; ?>

  </main>
 </body>
</html>
