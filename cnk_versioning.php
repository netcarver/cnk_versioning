<?php

$plugin=array(
'name'=>'cnk_versioning',
'version'=>'0.1.6',
'author'=>'Christian Nowak',
'author_uri'=>'http://www.cnowak.de',
'description'=>'Autoload Templates',
'type'=>'1',
);

@include_once('zem_tpl.php');

# --- BEGIN PLUGIN CODE ---
global $CNK_VER_OUTPUT_PATH, $CNK_VER_EXT, $CNK_VER_EXT_CSS;

$CNK_VER_OUTPUT_PATH = 'textpattern'.DS.'_templates'.DS.'versioning'.DS;
$CNK_VER_EXT = 'txp';
$CNK_VER_EXT_CSS = 'css';

/*
	DO NOT EDIT BELOW THIS LINE!
*/

$CNK_VER_OUTPUT_PATH = trim($CNK_VER_OUTPUT_PATH, DS ) . ($CNK_VER_OUTPUT_PATH ? DS : '');

global $CNK_VER_STRINGS;
if (!is_array($CNK_VER_STRINGS))
{
	$CNK_VER_STRINGS = array(
		'tab_versioning' => 'Versioning',
		'public_errors' => 'Errors while synchronising database and files',
		'edit_forbidden' => 'While cnk_versioning is enabled, {type} editing is disabled.',
		'edit_howto' => 'Use an external text editor to change the files in "{path}".',
		'write_linktext' => 'Write pages & forms to files',
		'install' => 'Install',
		'deinstall' => 'Deinstall',
		'dir_no_write' => 'Folder {dir} is not writable.',
		'err_processing' => 'There was an error while processing the {thing}.',
		'goback' => 'Back to menu&#8230;',
		'write_ok' => 'The {things} were successfully written to "{dir}".',
		'tab_writetofiles' => 'Write to files',
		'success' => 'Success!',
		'failure' => 'Failure!',
		'confirm' => 'Yes, I really want to continue',
		'warning' => 'There are already some files in the pages, forms or css directory, which will be overriden. Do you want to continue?'
	);
}

define( 'CNK_VER_PREFIX' , 'cnk_ver' );

register_callback( 'cnk_ver_enumerate_strings' , 'l10n.enumerate_strings' );
function cnk_ver_enumerate_strings($event , $step='' , $pre=0)
{
	global $CNK_VER_STRINGS;
	$r = array	(
				'owner'		=> 'mem_self_register',			#	Change to your plugin's name
				'prefix'	=> CNK_VER_PREFIX,				#	Its unique string prefix
				'lang'		=> 'en-gb',						#	The language of the initial strings.
				'event'		=> 'public',					#	public/admin/common = which interface the strings will be loaded into
				'strings'	=> $CNK_VER_STRINGS,			#	The strings themselves.
				);
	return $r;
}
function cnk_ver_gTxt($what,$args = array())
{
	global $CNK_VER_STRINGS, $textarray;

	$what = strtolower($what);
	$key = CNK_VER_PREFIX . '-' . $what;

	if (isset($textarray[$key]))
	{
		$str = $textarray[$key];
	}
	else
	{
		if (isset($CNK_VER_STRINGS[$what]))
			$str = $CNK_VER_STRINGS[$what];
		elseif (isset($textarray[$what]))
			$str = $textarray[$what];
		else
			$str = $what;
	}

	if( !empty($args) )
		$str = strtr( $str , $args );

	return $str;
}


if(@txpinterface == 'admin') 
{
	add_privs('cnk_versioning','1,2');
	register_tab('presentation', 'cnk_versioning', cnk_ver_gTxt('tab_versioning'));
	register_callback('cnk_ver_handler', 'cnk_versioning');
	register_callback('cnk_ver_disable_online_editing', 'page','',1);
	register_callback('cnk_ver_disable_online_editing', 'form','',1);
	register_callback('cnk_ver_disable_online_editing', 'css','',1);
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
		$forms = glob($CNK_VER_OUTPUT_PATH.'forms'.DS.'*.'.$CNK_VER_EXT);
		$pages = glob($CNK_VER_OUTPUT_PATH.'pages'.DS.'*.'.$CNK_VER_EXT);
		$css = glob($CNK_VER_OUTPUT_PATH.'css'.DS.'*.'.$CNK_VER_EXT_CSS);
		
		if ($forms !== false) $error = !cnk_ver_push_forms($forms);
		if ($pages !== false) $error = !cnk_ver_push_pages($pages);
		if ($css !== false) $error = !cnk_ver_push_css($css);
		
		if ($error) echo cnk_ver_gTxt('public_errors');
	}
}

function cnk_ver_push_forms($form_files)
{
	global $CNK_VER_OUTPUT_PATH, $CNK_VER_EXT;
	
	$forms = array();

	$rs = safe_rows_start('name,type,IFNULL(unix_timestamp(file_mod_time), 0) as mod_time', 'txp_form', '1=1');
	
	while ($rs && $r = nextRow($rs))
	{
		$forms[$CNK_VER_OUTPUT_PATH.'forms'.DS.$r['name'].'.'.$r['type'].'.'.$CNK_VER_EXT] = $r['mod_time'];
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
		$pages[$CNK_VER_OUTPUT_PATH.'pages'.DS.$r['name'].'.'.$CNK_VER_EXT] = $r['mod_time'];
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
		$css[$CNK_VER_OUTPUT_PATH.'css'.DS.$r['name'].'.'.$CNK_VER_EXT_CSS] = $r['mod_time'];
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
	global $CNK_VER_OUTPUT_PATH;
	pagetop(cnk_ver_gTxt('tab_versioning'), cnk_ver_gTxt('edit_forbidden' , array('{type}'=>$event)) );

	echo '<div style="margin: auto; text-align: center">';
	echo cnk_ver_gTxt('edit_howto',array('{path}'=> htmlspecialchars($CNK_VER_OUTPUT_PATH)));
	echo '</div>';

	global $event;
	$event = 'cnk_versioning_blackhole';
}

function cnk_ver_menu($message = '')
{
	pagetop(cnk_ver_gTxt('tab_versioning'), $message);
	
	echo '<div style="margin: auto; text-align: center"><ul>';
	
	echo '<li><a href="?event=cnk_versioning'.a.'step=cnk_ver_pull_all">'.cnk_ver_gTxt('write_linktext').'</a></li>';
	
	echo '</ul>';
	
	echo '<ul style="margin-top: 50px">';	
	
	echo '<li><a href="?event=cnk_versioning'.a.'step=cnk_ver_install">'.cnk_ver_gTxt('install').'</a></li>';
	
	echo '<li><a href="?event=cnk_versioning'.a.'step=cnk_ver_deinstall">'.cnk_ver_gTxt('deinstall').'</a></li>';

	echo '</ul>';
	
	echo '</div>';
}

function cnk_ver_pull_all()
{
	global $CNK_VER_OUTPUT_PATH, $CNK_VER_EXT, $CNK_VER_EXT_CSS;
	
	pagetop( cnk_ver_gTxt('tab_writetofiles'), '');
	
	echo '<div style="margin: auto; text-align: center">';
				
	if (gps('do_pull') || (glob('..'.DS.$CNK_VER_OUTPUT_PATH.'pages'.DS.'*.'.$CNK_VER_EXT) === false && glob('..'.DS.$CNK_VER_OUTPUT_PATH.'forms'.DS.'*.'.$CNK_VER_EXT) === false && glob('..'.DS.$CNK_VER_OUTPUT_PATH.'css'.DS.'*.'.$CNK_VER_EXT_CSS) === false))
	{
		$error = false;
		
		// test if folders exist and have write permissions
		if (@is_writable('..'.DS.$CNK_VER_OUTPUT_PATH.'forms'.DS) === false)
		{
			$error = true;
			echo cnk_ver_gTxt( 'dir_no_write' , array( '{path}' => DS.'forms'.DS ) ) . br . br;
		}
		
		if (@is_writable('..'.DS.$CNK_VER_OUTPUT_PATH.'pages'.DS) === false)
		{
			$error = true;
			echo cnk_ver_gTxt( 'dir_no_write' , array( '{path}' => DS.'pages'.DS ) ) . br . br;
		}
		
		if (@is_writable('..'.DS.$CNK_VER_OUTPUT_PATH.'css'.DS) === false)
		{
			$error = true;
			echo cnk_ver_gTxt( 'dir_no_write' , array( '{path}' => DS.'css'.DS ) ) . br . br;
		}
		
		if (!$error)
		{
				if (cnk_ver_pull_forms())
				{
					echo cnk_ver_gTxt('write_ok',array('{things}'=>'forms','{dir}'=>DS.'forms'.DS)).br.br;
				}
				else
				{
					echo cnk_ver_gTxt( 'err_processing' , array( '{thing}' => 'forms' ) ) . br . br;
				}
				
				if (cnk_ver_pull_pages())
				{
					echo cnk_ver_gTxt('write_ok',array('{things}'=>'pages','{dir}'=>DS.'pages'.DS)).br.br;
				}
				else
				{
					echo cnk_ver_gTxt( 'err_processing' , array( '{thing}' => 'pages' ) ) . br . br;
				}
				
				if (cnk_ver_pull_css())
				{
					echo cnk_ver_gTxt('write_ok',array('{things}'=>'styles','{dir}'=>DS.'css'.DS)).br.br;
				}
				else
				{
					echo cnk_ver_gTxt( 'err_processing' , array( '{thing}' => 'css' ) ) . br . br;
				}
		}
		
		echo '<br /><br /><a href="?event=cnk_versioning">'.cnk_ver_gTxt('goback').'</a>';
	}
	else
	{
		echo cnk_ver_gTxt('warning').br.br.
		'<a href="?event=cnk_versioning'.a.'step=cnk_ver_pull_all'.a.'do_pull=1">'.cnk_ver_gTxt('confirm').'</a><br /><br />'.		
		'<a href="?event=cnk_versioning">'.cnk_ver_gTxt('goback').'</a>';
	}
	
	echo '</div>';
}

function cnk_ver_pull_forms()
{
	global $CNK_VER_OUTPUT_PATH, $CNK_VER_EXT;
	
	$rs = safe_rows_start('name, type, form', 'txp_form', '1=1');
	
	while ($rs && $r = nextRow($rs))
	{
		if (@file_put_contents('..'.DS.$CNK_VER_OUTPUT_PATH.'forms'.DS.$r['name'].'.'.$r['type'].'.'.$CNK_VER_EXT, $r['form']) === false) return false;
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
		if (@file_put_contents('..'.DS.$CNK_VER_OUTPUT_PATH.'pages'.DS.$r['name'].'.'.$CNK_VER_EXT, $r['user_html']) === false) return false;
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
		if (@file_put_contents('..'.DS.$CNK_VER_OUTPUT_PATH.'css'.DS.$r['name'].'.'.$CNK_VER_EXT_CSS, base64_decode($r['css'])) === false) return false;
	}
	
	safe_update('txp_css', "file_mod_time = FROM_UNIXTIME('".time()."')", '1=1');
	
	return true;
}

function cnk_ver_install()
{
	pagetop(cnk_ver_gTxt('install').'-- cnk_versioning', '');
	
	echo '<div style="margin:auto; text-align:center">';
	
	if (cnk_ver_do_install())
	{
		echo graf(	cnk_ver_gTxt('success') );
	}
	else
	{
		echo graf(	cnk_ver_gTxt('failure') );
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
	pagetop(cnk_ver_gTxt('deinstall').'-- cnk_versioning', '');
	
	echo '<div style="margin:auto; text-align:center">';
	
	if (gps('do_deinstall'))
	{
		if (cnk_ver_do_deinstall())
		{
			echo graf(	cnk_ver_gTxt('success') );
		}
		else
		{
			echo graf(	cnk_ver_gTxt('failure') );
		}
	}
	else
	{
		echo '<a href="?event=cnk_versioning'.a.'step=cnk_ver_deinstall'.a.'do_deinstall=1">'.cnk_ver_gTxt('confirm').'</a>';
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
