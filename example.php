<?php

//ini_set('display_errors', 'On');

/**
 * MemAPC
 * A PHP Key-Value store using APC
 *
 * @package 	memapc
 * @version 	1.0.0
 * @author  	James Cunningham <http://jamescun.com>
 * @copyright	Copyright (C) 2011 James Cunningham.
 * @license 	BSD License
				or "Do what ever the hell you want I don't care" License
 */
	
	// Include the MemAPC Class
	require('memapc.class.php');

	// Initialise the MemAPC class into $mem
	$mem = new MemAPC('testapp');
	
	// Assign 'apple' to the key 'fruit'
	$mem->set ('fruit', 'apple');
	
	// Fetch the contents of the key 'fruit' and echo
	echo 'An ' . $mem->get ('fruit') . ' a day keeps the doctor away. ';
	
	// Delete the key 'fruit' from our cache
	$mem->delete ('fruit');
	
	// Fetch the contents of the key 'fruit' and echo, since deleted
	echo 'My favourite fruit is ' . $mem->get ('fruit') . 's.';

?>