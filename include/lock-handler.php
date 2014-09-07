<?php

/**
 * A helper class for file locking stuff.
 * Locks obtained through this class are shared with the entire request,
 * allowing multiple objects to get their own locks on the same file
 * without deadlocking internally.
 */
class LockHandler
{
	public static function getInstance()
	{
		static $instance = null;
		if ($instance === null)
		{
			$instance = new LockHandler();
		}
		return $instance;
	}

	private $handles = array();

	/**
	 * Gets an internally shared lock on the given file.
	 *
	 * If an exclusive lock is requested on a file already locked for sharing,
	 * this will throw an exception as there is no way to make the lock
	 * more restrictive without dropping it.
	 * Since an exclusive lock is already more restrictive than a shared
	 * one, requests for a shared lock when an exclusive one is already
	 * present are accepted.
	 *
	 * @param lockfile: The path to the file to lock.
	 *        It is recommended that this be an absolute path to avoid
	 *        attempting to lock the same file twice.
	 * @param exclusive: Whether or not to get an exclusive lock (the
	 *        alternative being a shared lock).
	 * @returns: The resource from opening the file, opened with file_open.
	 */
	public function lock($lockfile, $exclusive = true)
	{
		if (!isset($this->handles[$lockfile]))
		{
			$resource = file_lock($lockfile, $exclusive);
			$wrapper = new stdClass();
			$wrapper->count = 0;
			$wrapper->exclusive = $exclusive;
			$wrapper->handle = $resource;
			$this->handles[$lockfile] = $wrapper;
		}
		else
		{
			$wrapper = $this->handles[$lockfile];
			if ($exclusive && !$wrapper->exclusive)
			{
				throw new Exception("Cannot obtain exclusive lock on top of existing shared lock for '$lockfile'.", E_INTERNAL_ERROR);
			}
		}
		$wrapper->count++;
		return $wrapper->handle;
	}

	/**
	 * Releases a lock on the given file or resource.
	 *
	 * Note: if a shared lock was requested, but an exclusive one was already
	 * present, then the exclusive lock will remain in place until both have
	 * been cleared.
	 *
	 * @param resource: The path to the file to lock, or the resource from opening it.
	 *        It is recommended that this be the resource.
	 */
	public function unlock($resource)
	{
		$wrapper = null;
		$lockfile = null;

		if (is_resource($resource))
		{
			foreach ($this->handles as $path => $res)
			{
				if ($res->handle === $resource)
				{
					$wrapper = $res;
					$lockfile = $path;
					break;
				}
			}
		}
		else if (is_string($resource))
		{
			$lockfile = $resource;
			if (isset($this->handles[$lockfile]))
			{
				$wrapper = $this->handles[$lockfile];
			}
		}

		if ($wrapper === null)
		{
			throw new Exception("Cannot unlock unknown item '$resource'.", E_INTERNAL_ERROR);
		}

		$wrapper->count--;
		$ret = true;
		if ($wrapper->count === 0 && ($ret = file_unlock($wrapper->handle)))
		{
			unset($this->handles[$lockfile]);
		}
		return $ret;
	}

	/**
	 * Testing method.
	 * Returns the number of active repo hanldes.
	 * Intended to help catch potential deadlocks with other threads.
	 */
	public function handleCount()
	{
		return count($this->handles);
	}
}
