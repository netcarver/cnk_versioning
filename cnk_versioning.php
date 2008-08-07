<?php

$plugin=array(
'name'=>'cnk_versioning',
'version'=>'0.1.6',
'author'=>'Christian Nowak',
'author_uri'=>'http://www.cnowak.de',
'description'=>'Autoload Templates',
'type'=>'3',
);

@include_once('zem_tpl.php');

# --- BEGIN PLUGIN CODE ---
global $CNK_VER_OUTPUT_PATH, $CNK_VER_EXT, $CNK_VER_EXT_CSS;

$CNK_VER_OUTPUT_PATH = 'textpattern/_templates/versioning/';
$CNK_VER_EXT = 'txp';
$CNK_VER_EXT_CSS = 'css';

/*
	DO NOT EDIT BELOW THIS LINE!
*/

$CNK_VER_OUTPUT_PATH = trim($CNK_VER_OUTPUT_PATH, '/').($CNK_VER_OUTPUT_PATH?'/':'');

if(@txpinterface == 'admin') 
{
	add_privs('cnk_versioning','1,2');
	register_tab('presentation', 'cnk_versioning', "Versioning");
	register_callback('cnk_ver_handler', 'cnk_versioning');
	register_callback('cnk_ver_disable_online_editing', 'page');
	register_callback('cnk_ver_disable_online_editing', 'form');
	register_callback('cnk_ver_disable_online_editing', 'css');
}
else if (@txpinterface == 'public')
{
	register_callback('cnk_ver_textpattern', 'textpattern');
}

function cnk_ver_textpattern()
{
	global $production_status, $CNK_VER_OUTPUT_PATH, $CNK_VER_EXT, $CNK_VER_EXT_CSS;
	
	if ($production_status != 'live')
	{
		$error = false;
		
		// read all files
		$forms = glob($CNK_VER_OUTPUT_PATH.'forms/*.'.$CNK_VER_EXT);
		$pages = glob($CNK_VER_OUTPUT_PATH.'pages/*.'.$CNK_VER_EXT);
		$css = glob($CNK_VER_OUTPUT_PATH.'css/*.'.$CNK_VER_EXT_CSS);
		
		if ($forms !== false) $error = !cnk_ver_push_forms($forms);
		if ($pages !== false) $error = !cnk_ver_push_pages($pages);
		if ($css !== false) $error = !cnk_ver_push_css($css);
		
		if ($error) echo 'Errors while synchronising database and files';
	}
}

function cnk_ver_push_forms($form_files)
{
	global $CNK_VER_OUTPUT_PATH, $CNK_VER_EXT;
	
	$forms = array();

	$rs = safe_rows_start('name,type,IFNULL(unix_timestamp(file_mod_time), 0) as mod_time', 'txp_form', '1=1');
	
	while ($rs && $r = nextRow($rs))
	{
		$forms[$CNK_VER_OUTPUT_PATH.'forms/'.$r['name'].'.'.$r['type'].'.'.$CNK_VER_EXT] = $r['mod_time'];
	}
	
	for ($i=0; $i < count($form_files); $i++)
	{
		if (isset($forms[$form_files[$i]]) && $forms[$form_files[$i]] < filemtime($form_files[$i]))
		{
			cnk_ver_update_form($form_files[$i]);
		}
		else if (!isset($forms[$form_files[$i]]))
		{
			cnk_ver_update_form($form_files[$i], true);
		}
		$forms[$form_files[$i]] = 'processed';
	}
		
	// delete removed forms
	foreach ($forms as $key => $value)
	{
		if ($value != 'processed')
		{
			$cols = explode('.', basename($key, '.'.$CNK_VER_EXT));
			$name = $cols[0];
			$type = $cols[1];
			
			safe_delete('txp_form', "name = '$name' AND type = '$type'");
		}
	}

	return true;
}

function cnk_ver_update_form($filename, $create = false)
{
	global $CNK_VER_EXT;
	
	$cols = explode('.', basename($filename, '.'.$CNK_VER_EXT));

	$name = $cols[0];
	$type = $cols[1];
	$form = doSlash(file_get_contents($filename));
	
	if ($create)
	{
		safe_insert('txp_form', "name = '$name', type = '$type', form = '$form', file_mod_time = FROM_UNIXTIME('".time()."')");
	}
	else
	{
		safe_update('txp_form', "form = '$form', file_mod_time = FROM_UNIXTIME('".time()."')", "name = '$name' AND type = '$type'");
	}
}

function cnk_ver_push_pages($page_files)
{
	global $CNK_VER_OUTPUT_PATH, $CNK_VER_EXT;
	
	$pages = array();

	$rs = safe_rows_start('name,IFNULL(unix_timestamp(file_mod_time), 0) as mod_time', 'txp_page', '1=1');
	
	while ($rs && $r = nextRow($rs))
	{
		$pages[$CNK_VER_OUTPUT_PATH.'pages/'.$r['name'].'.'.$CNK_VER_EXT] = $r['mod_time'];
	}
	
	for ($i=0; $i < count($page_files); $i++)
	{
		if (isset($pages[$page_files[$i]]) && $pages[$page_files[$i]] < filemtime($page_files[$i]))
		{
			cnk_ver_update_page($page_files[$i]);
		}
		else if (!isset($pages[$page_files[$i]]))
		{
			cnk_ver_update_page($page_files[$i], true);
		}
		$pages[$page_files[$i]] = 'processed';
	}
		
	// delete removed pages
	foreach ($pages as $key => $value)
	{
		if ($value != 'processed')
		{
			$name = basename($key, '.'.$CNK_VER_EXT);
			
			safe_delete('txp_page', "name = '$name'");
		}
	}

	return true;
}

function cnk_ver_update_page($filename, $create = false)
{
	global $CNK_VER_EXT;
	
	$name = basename($filename, '.'.$CNK_VER_EXT);
	$user_html = doSlash(file_get_contents($filename));
	
	if ($create)
	{
		safe_insert('txp_page', "name = '$name', user_html = '$user_html', file_mod_time = FROM_UNIXTIME('".time()."')");
	}
	else
	{
		safe_update('txp_page', "user_html = '$user_html', file_mod_time = FROM_UNIXTIME('".time()."')", "name = '$name'");
	}
}

function cnk_ver_push_css($css_files)
{
	global $CNK_VER_OUTPUT_PATH, $CNK_VER_EXT_CSS;
	
	$css = array();

	$rs = safe_rows_start('name,IFNULL(unix_timestamp(file_mod_time), 0) as mod_time', 'txp_css', '1=1');
	
	while ($rs && $r = nextRow($rs))
	{
		$css[$CNK_VER_OUTPUT_PATH.'css/'.$r['name'].'.'.$CNK_VER_EXT_CSS] = $r['mod_time'];
	}
	
	for ($i=0; $i < count($css_files); $i++)
	{
		if (isset($css[$css_files[$i]]) && $css[$css_files[$i]] < filemtime($css_files[$i]))
		{
			cnk_ver_update_css($css_files[$i]);
		}
		else if (!isset($css[$css_files[$i]]))
		{
			cnk_ver_update_css($css_files[$i], true);
		}
		$css[$css_files[$i]] = 'processed';
	}
		
	// delete removed css files
	foreach ($css as $key => $value)
	{
		if ($value != 'processed')
		{
			$name = basename($key, '.'.$CNK_VER_EXT_CSS);
			
			safe_delete('txp_css', "name = '$name'");
		}
	}

	return true;
}

function cnk_ver_update_css($filename, $create = false)
{
	global $CNK_VER_EXT_CSS;
	
	$name = basename($filename, '.'.$CNK_VER_EXT_CSS);
	$css = doSlash(base64_encode(file_get_contents($filename)));
	
	if ($create)
	{
		safe_insert('txp_css', "name = '$name', css = '$css', file_mod_time = FROM_UNIXTIME('".time()."')");
	}
	else
	{
		safe_update('txp_css', "css = '$css', file_mod_time = FROM_UNIXTIME('".time()."')", "name = '$name'");
	}
}

function cnk_ver_handler($event, $step)
{
	if(!$step or !in_array($step, array('cnk_ver_menu',
										'cnk_ver_pull_all',
										'cnk_ver_install',
										'cnk_ver_deinstall')))
	{
		cnk_ver_menu();
	} 
	else
	{
		$step();
	}
}

function cnk_ver_disable_online_editing($event, $step)
{
	// clear ob
	ob_end_clean();
	
	// return error message
	
	pagetop('Versioning');
	
	echo '<div style="margin: auto; text-align: center"><ul>';
	
	echo 'While cnk_versioning is enabled, this function is disabled.';
	
	echo '</div>';
}

function cnk_ver_menu($message = '')
{
	pagetop('Versioning', $message);
	
	echo '<div style="margin: auto; text-align: center"><ul>';
	
	echo '<li><a href="?event=cnk_versioning'.a.'step=cnk_ver_pull_all">Write pages & forms to files</a></li>';
	
	echo '</ul>';
	
	echo '<ul style="margin-top: 100px">';	
	
	echo '<li><a href="?event=cnk_versioning'.a.'step=cnk_ver_install">Install</a></li>';
	
	echo '<li><a href="?event=cnk_versioning'.a.'step=cnk_ver_deinstall">Deinstall</a></li>';

	echo '</ul>';
	
	echo '</div>';
}

function cnk_ver_pull_all()
{
	global $CNK_VER_OUTPUT_PATH, $CNK_VER_EXT, $CNK_VER_EXT_CSS;
	
	pagetop('Write to files', '');
	
	echo '<div style="margin: auto; text-align: center">';
				
	if (gps('do_pull') || (glob('../'.$CNK_VER_OUTPUT_PATH.'pages/*.'.$CNK_VER_EXT) === false && glob('../'.$CNK_VER_OUTPUT_PATH.'forms/*.'.$CNK_VER_EXT) === false && glob('../'.$CNK_VER_OUTPUT_PATH.'css/*.'.$CNK_VER_EXT_CSS) === false))
	{
		$error = false;
		
		// test if folders exist and have write permissions
		if (@is_writable('../'.$CNK_VER_OUTPUT_PATH.'forms/') === false)
		{
			$error = true;
			echo 'Folder "/forms/" is not writable.<br /><br />';
		}
		
		if (@is_writable('../'.$CNK_VER_OUTPUT_PATH.'pages/') === false)
		{
			$error = true;
			echo 'Folder "/pages/" is not writable.<br /><br />';
		}
		
		if (@is_writable('../'.$CNK_VER_OUTPUT_PATH.'css/') === false)
		{
			$error = true;
			echo 'Folder "/css/" is not writable.';
		}
		
		if (!$error)
		{
				if (cnk_ver_pull_forms())
				{
					echo 'Forms were successfully written to the "/forms/" directory.<br /><br />';
				}
				else
				{
					echo 'There was an error while processing forms.<br /><br />';
				}
				
				if (cnk_ver_pull_pages())
				{
					echo 'Pages were successfully written to the "/pages/" directory.<br /><br />';
				}
				else
				{
					echo 'There was an error while processing pages.<br /><br />';
				}
				
				if (cnk_ver_pull_css())
				{
					echo 'Styles were successfully written to the "/css/" directory.';
				}
				else
				{
					echo 'There was an error while processing css.';
				}
		}
		
		echo '<br /><br /><a href="?event=cnk_versioning">Back to menu...</a>';
	}
	else
	{
		echo 'There are already some files in the pages, forms or css directory, which will be overriden. Do you want to continue?<br /><br />'.
		
		'<a href="?event=cnk_versioning'.a.'step=cnk_ver_pull_all'.a.'do_pull=1">Yes, overwrite existing files...</a><br /><br />'.
		
		'<a href="?event=cnk_versioning">No, bring me back to the menu...</a>';
	}
	
	echo '</div>';
}

function cnk_ver_pull_forms()
{
	global $CNK_VER_OUTPUT_PATH, $CNK_VER_EXT;
	
	$rs = safe_rows_start('name, type, form', 'txp_form', '1=1');
	
	while ($rs && $r = nextRow($rs))
	{
		if (@file_put_contents('../'.$CNK_VER_OUTPUT_PATH.'forms/'.$r['name'].'.'.$r['type'].'.'.$CNK_VER_EXT, $r['form']) === false) return false;
	}
	
	safe_update('txp_form', "file_mod_time = FROM_UNIXTIME('".time()."')", '1=1');
	
	return true;
}

function cnk_ver_pull_pages()
{
	global $CNK_VER_OUTPUT_PATH, $CNK_VER_EXT;
	
	$rs = safe_rows_start('name, user_html', 'txp_page', '1=1');
	
	while ($rs && $r = nextRow($rs))
	{
		if (@file_put_contents('../'.$CNK_VER_OUTPUT_PATH.'pages/'.$r['name'].'.'.$CNK_VER_EXT, $r['user_html']) === false) return false;
	}
	
	safe_update('txp_page', "file_mod_time = FROM_UNIXTIME('".time()."')", '1=1');
	
	return true;
}

function cnk_ver_pull_css()
{
	global $CNK_VER_OUTPUT_PATH, $CNK_VER_EXT_CSS;
	
	$rs = safe_rows_start('name, css', 'txp_css', '1=1');
	
	while ($rs && $r = nextRow($rs))
	{
		if (@file_put_contents('../'.$CNK_VER_OUTPUT_PATH.'css/'.$r['name'].'.'.$CNK_VER_EXT_CSS, base64_decode($r['css'])) === false) return false;
	}
	
	safe_update('txp_css', "file_mod_time = FROM_UNIXTIME('".time()."')", '1=1');
	
	return true;
}

function cnk_ver_install()
{
	pagetop('Versioning Plugin Installation', '');
	
	echo '<div style="margin:auto; text-align:center">';
	
	if (cnk_ver_do_install())
	{
		echo '<p>Installation was successful</p>';
	}
	else
	{
		echo '<p>Installation aborted</p>';
	}

	echo '</div>';
}

function cnk_ver_do_install()
{
	$sql = "ALTER TABLE ".safe_pfx('txp_form')." ADD `file_mod_time` DATETIME NULL;ALTER TABLE ".safe_pfx('txp_page')." ADD `file_mod_time` DATETIME NULL;ALTER TABLE ".safe_pfx('txp_css')." ADD `file_mod_time` DATETIME NULL";
	
	if (!cnk_ver_batch_query($sql))
	{
		return false;
	}
	else
	{	
		return true;
	}
}

function cnk_ver_deinstall()
{
	pagetop('Versioning Plugin Deinstallation', '');
	
	echo '<div style="margin:auto; text-align:center">';
	
	if (gps('do_deinstall'))
	{
		if (cnk_ver_do_deinstall())
		{
			echo '<p>Deinstallation was successful</p>';
		}
		else
		{
			echo '<p>Deinstallation aborted</p>';
		}
	}
	else
	{
		echo '<a href="?event=cnk_versioning'.a.'step=cnk_ver_deinstall'.a.'do_deinstall=1">Yes, I really want to deinstall</a>';
	}
	
	echo '</div>';
}

function cnk_ver_do_deinstall()
{
	$sql = "ALTER TABLE ".safe_pfx('txp_form')." DROP `file_mod_time`;ALTER TABLE ".safe_pfx('txp_page')." DROP `file_mod_time`;ALTER TABLE ".safe_pfx('txp_css')." DROP `file_mod_time`;";
	
	if (!cnk_ver_batch_query($sql))
	{
		return false;
	}
	else
	{	
		return true;
	}
}

function cnk_ver_batch_query ($p_query, $p_transaction_safe = true) 
{
	if ($p_transaction_safe) 
	{
		$p_query = 'START TRANSACTION;' . $p_query . '; COMMIT;';
	}
	  
	$query_split = preg_split ("/[;]+/", $p_query);
	
	foreach ($query_split as $command_line) 
	{
		$command_line = trim($command_line);
	
		if ($command_line != '') 
		{
			$query_result = safe_query($command_line);
		
			if ($query_result === false) 
			{
				break;
			}
		}
	}
	
	return $query_result;
}

# --- END PLUGIN CODE ---
if (0) {
?>
<!--
# --- BEGIN PLUGIN HELP ---

# --- END PLUGIN HELP ---
-->
<?php
}
?>
