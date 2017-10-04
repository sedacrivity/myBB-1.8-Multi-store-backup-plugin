<?php
// Disallow direct access to this file for security reasons
if(!defined('IN_MYBB'))
{
	die('This file cannot be accessed directly.');
}

if(defined('IN_ADMINCP'))
{
	// Nothing to hook 
	
}
else
{
	// Nothing to do when we are not in admin
}


function multistoragebackup_info()
{

	// Info
	return array(
		'name'		=> 'Multi-Storage Backup Utility',
		'description'	=> 'Multi storage backup utility for a MyBB forum!',
		'website'	=> 'http://www.sedacrivity.com',
		'author'	=> 'Sedacrivity',
		'authorsite'	=> 'http://www.sedacrivity.com',
		'version'	=> '1.0',
		'codename'	=> str_replace('.php', '', basename(__FILE__)),
		'compatibility' => '18*'
	);
}

function multistoragebackup_is_installed()
{
	global $db;

	// Check if our settings exist
	$query = $db->simple_select("settinggroups","*","name='multistoragebackup'");

	// If so
	if($db->num_rows($query) == 1)
	{
		// We are installed
		return true;
	}
	
	// We are not installed
	return false;
}

function multistoragebackup_install()
{

	global $db;

	// Settings Group		
	$setting_group = array(
	    'name' => 'multistoragebackup',
	    'title' => 'Multi-Storage Backup Settings',
	    'description' => 'Configure the backup of your forum including FTP.',
	    'disporder' => 50,
	    'isdefault' => 0 );

	$gid = $db->insert_query("settinggroups", $setting_group);	

	// Actual settings
	$setting = array();

	$setting['name'] = 'multistoragebackup_ftp';
	$setting['title'] = 'Backup to FTP?';
	$setting['description'] = 'Should we create an external backup via FTP?';
	$setting['optionscode'] = 'yesno';
	$setting['value'] = '0';
	$setting['disporder'] = 1;
	$setting['gid'] = $gid;
	$db->insert_query('settings',$setting);
	
	$setting['name'] = 'multistoragebackup_ftphost';
	$setting['title'] = 'FTP Host';
	$setting['description'] = 'Host and port of the FTP server you want to create a backup on. If not port is used, the default (21) will be used. Ex: ftp.mysite.com:21';
	$setting['optionscode'] = 'text';
	$setting['value'] = 'localhost:21';
	$setting['disporder'] = 2;
	$setting['gid'] = $gid;
	$db->insert_query('settings',$setting);
	
	$setting['name'] = 'multistoragebackup_ftpuser';
	$setting['title'] = 'FTP User';
	$setting['description'] = 'Username to connect to the FTP server';
	$setting['optionscode'] = 'text';
	$setting['value'] = 'root';
	$setting['disporder'] = 3;
	$setting['gid'] = $gid;
	$db->insert_query('settings',$setting);
	
	$setting['name'] = 'multistoragebackup_ftppass';
	$setting['title'] = 'FTP Password';
	$setting['description'] = 'Password for the FTP server';
	$setting['optionscode'] = 'passwordbox';
	$setting['value'] = '';
	$setting['disporder'] = 4;
	$setting['gid'] = $gid;
	$db->insert_query('settings',$setting);
	
	$setting['name'] = 'multistoragebackup_ftppath';
	$setting['title'] = 'FTP File Path';
	$setting['description'] = 'Path to upload the file to. NOTE: This requires a trailing slash.';
	$setting['optionscode'] = 'text';
	$setting['value'] = '/';
	$setting['disporder'] = 5;
	$setting['gid'] = $gid;
	$db->insert_query('settings',$setting);
	
	$setting['name'] = 'multistoragebackup_ftpfilename_prefix';
	$setting['title'] = 'FTP File Name prefix';
	$setting['description'] = 'Optional prefix for the filename written to the FTP location. The entered value will be prefixed with a following underscore to the default backup file name.';
	$setting['optionscode'] = 'text';
	$setting['value'] = '';
	$setting['disporder'] = 6;
	$setting['gid'] = $gid;
	$db->insert_query('settings',$setting);	
	
	$setting['name'] = 'multistoragebackup_ftpdirym';
	$setting['title'] = 'Use Year-Month Subfolders';
	$setting['description'] = 'Optional. Creates subfolders using the year and month to further organize the stored backup files.';
	$setting['optionscode'] = 'yesno';
	$setting['value'] = '0';
	$setting['disporder'] = 7;
	$setting['gid'] = $gid;
	$db->insert_query('settings',$setting);
	
	// Rebuild settings data
	rebuild_settings();
}

function multistoragebackup_uninstall()
{
	global $db;

	// Remove our settings
	$db->delete_query("settinggroups","name='multistoragebackup'");
	$db->delete_query("settings","name LIKE 'multistoragebackup_%'");
	
	// Rebuild settings data
	rebuild_settings();
}

function multistoragebackup_activate()
{
	global $cache, $mybb, $db;
	
	// Require task functionality
	require_once  MYBB_ROOT."/inc/functions_task.php";
	
	// Create a new task ( schedule backup every day at midnight )
	$new_task = array(
		"title" => $db->escape_string("Multi-Storage Database Backup"),
		"description" => $db->escape_string("Backup your database to a file and store on multiple locations"),
		"file" => $db->escape_string("multistoragebackup"),
		"minute" => $db->escape_string("0"),
		"hour" => $db->escape_string("0"),
		"day" => $db->escape_string("*"),
		"month" => $db->escape_string("*"),
		"weekday" => $db->escape_string("*"),
		"enabled" => intval("1"),
		"logging" => intval("1")
	);

	// Next potential run
	$new_task['nextrun'] = fetch_next_run($new_task);
	
	// Insert 
	$db->insert_query("tasks", $new_task);
	
	// Update the tasks
	$cache->update_tasks();
}

function multistoragebackup_deactivate()
{

	global $cache, $mybb, $db;

	// Require task functionality
	require_once  MYBB_ROOT."/inc/functions_task.php";

	// Delete the task(s) using our plugin task
	$db->delete_query("tasks","file='multistoragebackup'");

	// Update the tasks
	$cache->update_tasks();
}


?>