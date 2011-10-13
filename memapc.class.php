<?php

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

interface iMemAPC {
	// --- Set Key ---
	// Set a value in the APC Key-Value store
	// Where: Key is used as an identifier
	//        Value is the data to be stored
	//        TTL is the expire time in seconds, after which it is deleted
	// boolean SET (string KEY, mixed VALUE, int TTL) ;
	
	public function set ($key, $value, $ttl);
	
	
	// --- Get Key ---
	// Retrieve a value from the APC Key-Value store
	// Where: Key is used as the identifier
	// mixed GET (string KEY);
	
	public function get ($key);
	
	
	// --- Key Exist ---
	// Check if a key exists within the APC Key-Value store
	// Where: Key is used as the identifier
	// boolean EXIST (string KEY);
	
	public function exist ($key);
	
	
	// --- Delete Key ---
	// Delete a value from the APC Key-Value store
	// Where: Key is used as the identifier
	// boolean DELETE ($key);
	
	public function delete ($key);
	
	
	// --- Cache Info ---
	// Returns stdClass containing statistics related
	// to the user cache
	// stdClass CACHE_INFO ( );
	
	public function cache_info ();
}

class MemAPC implements iMemAPC {
	
	/* --- VARIABLE DECLERATION ---
	   -------------------------------------------------- */
	
	private $prefix;		// Storage Key Prefix
	
	
	/* --- GENERIC FUNCTIONS ---
	   -------------------------------------------------- */
	
	function __construct($prefix) {
		if (extension_loaded('apc')) {
			// APC is installed
			$this->prefix = $prefix;
		} else {
			// APC is not installed
			trigger_error('APC does not appear to be installed, cannot initialise MemAPC', E_USER_WARNING);
			return false;
		}
	}
	
	
	private function keyname ($key) {
		// Pattern used for consistant key names
		return sha1($this->prefix . '::' . $key);
	}
	
	
	/* --- STORAGE FUNCTIONS ---
	   -------------------------------------------------- */
	
	public function set ($key, $value, $ttl = 0) {
		if (($key == '') || ($value == '')) {return false;}
		
		$key = $this->keyname($key);
		
		return apc_store($key, $value, $ttl);
	}
	
	public function get ($key) {
		if ($key == '') {return false;}
		
		$key = $this->keyname($key);
		
		return apc_fetch($key);
	}
	
	public function exist ($key) {
		if ($key == '') {return false;}
		
		$key = $this->keyname($key);
		
		return apc_exist($key);
	}
	
	public function delete ($key) {
		if ($key == '') {return false;}
		
		$key = $this->keyname($key);
		
		return apc_delete($key);
	}
	
	
	/* --- STATISTIC FUNCTIONS ---
	   -------------------------------------------------- */
	
	public function cache_info () {
		$output = new stdClass;		// Initialise Empty Class for output
		
		foreach (apc_cache_info('user') as $key => $value) {
			if ($key == 'cache_list') continue;				// Skip Individual File Stats
			if ($key == 'slot_distribution') continue;		// Skip Individual File Slots
			
			$output->{$key} = $value;
		}
		
		return $output;
	}
}

?>