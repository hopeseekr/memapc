<?php

/**
 * MemStore
 * A PHP Key-Value store using APC, Memcache, or temporary files.
 *
 * @package     memstore
 * @version     2.0.0
 * @author      James Cunningham <http://jamescun.com>
 * @author      Theodore R. Smith <theodore@phpexperts.pro>
 * @copyright	Copyright (C) 2011 James Cunningham, Theodore R. Smith
 * @license     BSD License
                or "Do what ever the hell you want I don't care" License
 * @license     OSSAL http://people.freebsd.org/~seanc/ossal/
 *              or "Do whatever the hell you want, as long as you don't try to
 *              impose on anyone else's freedoms: E.g. you may not use this code,
 *              in any way, with a GPL-licensed application, without permission from
 *              the authors."
 */

interface iMemoryStoreDriver {
	// --- Set Key ---
	// Set a value in the APC Key-Value store
	// Where: Key is used as an identifier
	//        Value is the data to be stored
	//        TTL is the expire time in seconds, after which it is deleted
	// boolean SET(string KEY, mixed VALUE, int TTL) ;
	
	public function set($key, $value, $ttl);
	
	
	// --- Get Key ---
	// Retrieve a value from the APC Key-Value store
	// Where: Key is used as the identifier
	// mixed GET(string KEY);
	
	public function get($key);
	
	
	// --- Key Exist ---
	// Check if a key exists within the APC Key-Value store
	// Where: Key is used as the identifier
	// boolean EXIST(string KEY);
	
	public function exist($key);
	
	
	// --- Delete Key ---
	// Delete a value from the APC Key-Value store
	// Where: Key is used as the identifier
	// boolean DELETE($key);
	
	public function delete($key);
}

// Uses the composite pattern + dynamic injection.
class MemStore implements iMemoryStoreDriver
{
	/** @var iMemoryStoreDriver **/
	protected $driver;

	public function __construct(iMemoryStoreDriver $driver)
	{
		$this->driver = $driver;
	}

	public function set($key, $value, $ttl = 0)
	{
		return $this->driver->set($key, $value, $ttl);
	}

	public function get($key)
	{
		return $this->driver->get($key);
	}

	public function exist($key)
	{
		return $this->driver->exist($key);
	}

	public function delete($key)
	{
		return $this->driver->delete($key);
	}
}


class MemoryStoreException extends RuntimeException
{
    const EXTENSION_NOT_AVAILABLE = 'Could not find extension';
	const COULD_NOT_STORE = 'Could not store the value';
	const COULD_NOT_FETCH = 'Could not retrieve the value';
}

abstract class aMemoryStoreDriver
{
    // Storage Key Prefix
    protected $prefix;

	protected function keyname($key)
	{
		// Pattern used for consistant key names
		return sha1($this->prefix . '::' . $key);
	}
}

class MemStoreDriver_APC extends aMemoryStoreDriver implements iMemoryStoreDriver
{
	function __construct($prefix = 'apc')
    {
		if (!extension_loaded('apc'))
		{
			// APC is not installed
			throw new MemoryStoreException(MemoryStoreException::EXTENSION_NOT_AVAILABLE);
		}

		// APC is installed
		$this->prefix = $prefix;
	}


	public function set($key, $value, $ttl = 0)
	{
		if (($key == '') || ($value == '')) { return false; }
		
		$key = $this->keyname($key);
		
		if (!apc_store($key, $value, $ttl))
		{
			throw new MemoryStoreException(MemoryStoreException::COULD_NOT_STORE);
		}
	}
	
	public function get($key)
	{
		if ($key == '') { return false; }
		
		$key = $this->keyname($key);
		
		return apc_fetch($key);
	}
	
	public function exist($key)
	{
		if ($key == '') { return false; }
		
		$key = $this->keyname($key);
		
		return apc_exist($key);
	}
	
	public function delete($key)
	{
		if ($key == '') { return false; }
		
		$key = $this->keyname($key);
		
		return apc_delete($key);
	}
	
	public function cache_info()
	{
		$output = new stdClass;		// Initialise Empty Class for output
		
		foreach (apc_cache_info('user') as $key => $value) {
			if ($key == 'cache_list') continue;				// Skip Individual File Stats
			if ($key == 'slot_distribution') continue;		// Skip Individual File Slots
			
			$output->{$key} = $value;
		}
		
		return $output;
	}
}

class MemStoreDriver_Memcache extends aMemoryStoreDriver implements iMemoryStoreDriver
{
	/** @var Memcache */
	protected $memcache;

	public function __construct($prefix = 'memcache', array $serverPool = null)
	{
		if (!extension_loaded('memcache'))
		{
			// Memcache is not installed
			throw new MemoryStoreException(MemoryStoreException::EXTENSION_NOT_AVAILABLE);
		}

		// Memcache is installed
		$this->prefix = $prefix;

		$this->memcache = new Memcache;

		if (is_null($serverPool))
		{
			$serverPool = array(array('host' => 'locahost', 'port' => 11211));
		}

		foreach ($serverPool as $server)
		{
			$this->memcache->addServer($server['host'], $server['port']);
		}
	}

	public function set($key, $value, $ttl = 0)
	{
		if (($key == '') || ($value == '')) { return false; }

		$key = $this->keyname($key);

		if (!$this->memcache->set($key, $value, $ttl))
		{
			throw new MemoryStoreException(MemoryStoreException::COULD_NOT_STORE);
		}
	}

	public function get($key)
	{
		if ($key == '') { return false; }

		$key = $this->keyname($key);

		return $this->memcache->get($key);
	}

	// Memcache does not have a mechanism to see if a key exists, so this function always returns true.
	public function exist ($key)
	{
		return true;
	}

	public function delete($key)
	{
		if ($key == '') { return false; }

		$key = $this->keyname($key);

		return $this->memcache->delete($key);
	}
}

class MemStoreDriver_File extends aMemoryStoreDriver implements iMemoryStoreDriver
{
	protected $tempDirectory;

	public function __construct($prefix = 'memstore')
	{
		$this->prefix = $prefix;
		$tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $prefix;

		if (!file_exists($tempDir))
		{
			if (!mkdir($tempDir))
			{
				throw new MemoryStoreException("Could not create temporary directory in $tempDir.");
			}
		}
	}

	protected function getTempFilename($key)
	{
		$key = $this->keyname($key);
		$filename = $this->tempDirectory . DIRECTORY_SEPARATOR . $key;

		return $filename;
	}

	public function set($key, $value, $ttl = 0)
	{
		if (($key == '') || ($value == '')) { return false; }

		$filename = $this->getTempFilename($key);

		$data = serialize($value);
		if (!file_put_contents($filename, $data))
		{
			throw new MemoryStoreException(MemoryStoreException::COULD_NOT_STORE);
		}
	}

	public function get($key)
	{
		if ($key == '') { return false; }

		$filename = $this->getTempFilename($key);

		if (!($serializedData = file_get_contents($filename)))
		{
			throw new MemoryStoreException(MemoryStoreException::COULD_NOT_FETCH);
		}

		return unserialize($serializedData);
	}

	// Memcache does not have a mechanism to see if a key exists, so this function always returns true.
	public function exist ($key)
	{
		return true;
	}

	public function delete($key)
	{
		if ($key == '') { return false; }

		$filename = $this->getTempFilename($key);
		unlink($filename);
	}
}
