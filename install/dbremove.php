<?php
/**
 *
 * This software is a derived product, based on:
 * @name     	PortaMX-SubForums
 * @copyright	PortaMx Corp. http://portamx.com (SMF Version)
 *
 * This software is converted to ElkArte:
 * @convertor  	ahrasis http://elkarte.ahrasis.com (ElkArte Version)
 * @license 	BSD http://opensource.org/licenses/BSD-3-Clause
 * @name     	SFA: Sub Forums Addon
 *
 */

global $db_prefix, $user_info, $boardurl, $txt;

// Load the SSI.php
if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('ELK'))
{
	function _write($string) { echo $string; }

	require_once(dirname(__FILE__) . '/SSI.php');

	// on manual installation you have to logged in
	if(!$user_info['is_admin']) 
	{
		if($user_info['is_guest']) 
		{
			echo '<b>', $txt['admin_login'],':</b><br />';
			ssi_login($boardurl.'/dbinstall.php');
			die();
		}
		else 
		{
			loadLanguage('Errors');
			fatal_error($txt['cannot_admin_forum']);
		}
	}
}
// no SSI.php and no ELK?
elseif (!defined('ELK'))
	die('<b>Error:</b> SSI.php not found. Please verify you put this in the same place as ElkArte\'s index.php.');
else
{
	function _write($string) { return; };
}

// split of dbname (mostly for SSI)
$pref = explode('.', $db_prefix);
if(!empty($pref[1]))
	$pref = $pref[1];
else
	$pref = $db_prefix;

// Load the ELK DB Functions
if (ELK == 'SSI') {
	db_extend('packages');
	db_extend('extra');
}

/********************
* Define the tables *                      
*********************/ 
$tabledate = array(
	'subforums',
);

// loop through each table
foreach($tabledate as $tblname)
{
	// check if the table exist
	_write('Processing Table "'. $pref . $tblname .'".<br />');
	$tablelist = $db->list_table(false, $pref. $tblname);
	if(!empty($tablelist) && in_array($pref . $tblname, $tablelist))
	{
		// drop table
		$db->drop_table('{db_prefix}'. $tblname);
		_write('.. Table "'. $pref . $tblname .'" successful dropped.<br /><br />');
	}
	else
		_write('.. Table "'. $pref . $tblname .'" not exist.<br /></br />');
}

// done
_write('dbremove done.');
?>