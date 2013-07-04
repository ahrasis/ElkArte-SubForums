<?php
/**
* Subforums-DB.php
*
* Software Version: SMF Subforums v1.41
* Software by: PortaMx corp.
**/

if (!defined('SMF'))
	die('Hacking attempt...');

// check the db sting for Subforums
function SubForums_dbcallback($matches, $query)
{
	global $user_info, $db_prefix, $modSettings;

	if ($matches[1] === 'db_prefix')
		return $db_prefix;

	// if in subforum add find_in_set(id_board or id_cat)
	if ($matches[1] === 'query_see_board')
	{
		preg_match('/([a-zA-Z\.]+(id_cat|id_board)).?/is', $query, $cols);
		if(!empty($modSettings['subforums'][$_SERVER['SERVER_NAME']]) && isset($cols[2]))
			return $user_info['query_see_board'] .' AND FIND_IN_SET('. $cols[1] .', \''. ($cols[2] == 'id_cat' ? $modSettings['subforums'][$_SERVER['SERVER_NAME']]['cats'] : $modSettings['subforums'][$_SERVER['SERVER_NAME']]['boards']) .'\') != 0';
		else
			return $user_info['query_see_board'];
	}

	// if in subforum add find_in_set(id_board or id_cat)
	if ($matches[1] === 'query_wanna_see_board')
	{
		preg_match('/([a-zA-Z\.]+(id_cat|id_board)).?/is', $query, $cols);
		if(!empty($modSettings['subforums'][$_SERVER['SERVER_NAME']]) && isset($cols[2]))
			return $user_info['query_wanna_see_board'] .' AND FIND_IN_SET('. $cols[1] .', \''. ($cols[2] == 'id_cat' ? $modSettings['subforums'][$_SERVER['SERVER_NAME']]['cats'] : $modSettings['subforums'][$_SERVER['SERVER_NAME']]['boards']) .'\') != 0';
		else
			return $user_info['query_wanna_see_board'];
	}

	// special tag.. add find_in_set(...)
	if ($matches[1] === 'subforums_see_board')
	{
		if(!empty($modSettings['subforums'][$_SERVER['SERVER_NAME']]) && isset($matches[2]))
			return ' AND FIND_IN_SET('. str_replace('-', '.', $matches[2]) .', \''. $modSettings['subforums'][$_SERVER['SERVER_NAME']]['boards'] .'\') != 0';
		else
			return '';
	}
	return null;
}

// get id_cat or id_board in the query string
function SubForums_getCols($db_string)
{
	global $db_callback, $db_connection;

	preg_match('/([a-zA-Z\.]+(id_cat|id_board)).?/is', $db_string, $colfound);

	// This is needed by the callback function.
	return	array($colfound, $db_values, $connection == null ? $db_connection : $connection);
}
?>