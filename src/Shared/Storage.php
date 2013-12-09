<?php namespace Shared;

use RuntimeException;
use LogicException;

/**
 * Shared memory storage wrapper
 * @class
 */
class Storage
{
	/**
	 * Default shm key, unique enough
	 * @const string
	 */
	const DEFAULT_SHM_KEY = 'really-very-unque-key';

	/**
	 * Shared memory create mode
	 * Attempt to open or create with r/w permissions
	 * @const string
	 */
	const SHM_CREATE_MODE = 'c';

	/**
	 * Attempt to open shm sgement for r/w
	 * @const string
	 */
	const SHM_OPEN_MODE = 'w';

	/**
	 * Shared memory create permissions
	 * @const string
	 */
	const SHM_CREATE_PERMISSIONS = 0644;

	/**
 	 * Default shared memory segment size (1 MB)
	 * @const int
	 */
	const DEFAULT_SHM_SIZE = 1048576;

	/**
	 * Size of field which stores data array size
	 * Starts at zero byte of shm segment
	 * @const int
	 */
	const SHM_DATA_OFFSET = 10;

	/**
	 * Destroy flag
	 * @var bool
	 */
	protected $destroyed = false;

	/**
	 * String key for shared memory segment
	 * @var string
	 */
	protected $key;

	/**
	 * Create shared memory storage with given key
	 * @param string $key
	 */
	public function __construct($size = self::DEFAULT_SHM_SIZE, $key = self::DEFAULT_SHM_KEY) {
		$this->size = (int) $size;
		$this->key  = (string) $key;

		$this->setupSegment();
	}

	/**
	 * Get value from storage
	 * @param string $key
	 * @return mixed
	 */
	public function get($key, $default = null) {
		$this->check();
		$value = $this->readValue($key);
		return is_null($value) ? $default : $value;
	}

	/**
	 * Store data to storage
	 * @param string $key
	 * @param string $value
	 */
	public function set($key, $value) {
		$this->check();
		return $this->writeValue($key, $value);
	}

	/**
	 * Remove data from storage
	 * @param string $key
	 */
	public function forget($key) {
		$this->check();
		return $this->unsetValue($key);
	}

	/**
 	 * Returns current shm segment identifier
	 * @return int
	 */
	public function getIdentifier() {
		return md5($this->key);
	}

	/**
	 * Destroy shared memory segment
	 * @throws RuntimeException
	 */
	public function destroy() {
		if (false === shmop_delete($this->shm)) {
			throw new RuntimeException(sprintf('Unable to destroy shared memory segment id: %s', $this->shm));
		}

		$this->destroyed = true;
	}

	/**
	 * Check whether shm sgement is destroyed
	 * @throws LogicException
	 */
	protected function check() {
		if ($this->destroyed) {
			throw new LogicException(sprintf('Trying to access destroyed shared memory segment, oh dear!'));
		}
	}

	/**
	 * Create or open shared memory segment using current key
	 */
	protected function setupSegment() {
		// Attempt to open shm segment
		$this->shm = @shmop_open($this->getIdentifier(), self::SHM_OPEN_MODE, 0, 0);

		// If segment doesn't exist init new segment
		if (false === $this->shm) {
			$this->createSegment();
		}
	}

	/**
	 * Create new shm segment and write base meta data
	 */
	protected function createSegment() {
		$this->shm = shmop_open(
			$this->getIdentifier(),
			self::SHM_CREATE_MODE,
			self::SHM_CREATE_PERMISSIONS,
			$this->size
		);

		if (false === $this->shm) {
			throw new RuntimeException(sprintf('Unable to create shared memory segment with key: %s', $this->getIdentifier()));
		}

		$this->updateSize(0);
	}

	/**
	 * Update size field
	 * @param int size
	 */
	protected function updateSize($size) {
		$size = sprintf('%' . self::SHM_DATA_OFFSET . 'd', intval($size));
		return $this->write(0, $size);
	}

	/**
	 * Read size field
	 * @return int
	 */
	protected function readSize() {
		return intval($this->read(0, self::SHM_DATA_OFFSET));
	}

	/**
	 * Write value to shm segment
	 * @param string $key
	 * @param string $value
	 */
	protected function writeValue($key, $value) {
		$data = $this->readData();
		$data[$key] = $value;
		$this->writeData($data);
	}

	/**
	 * Removes item from storage
	 * @param string $key
	 */
	protected function unsetValue($key) {
		$data = $this->readData();
		unset($data[$key]);
		$this->writeData($data);
	}	

	/**
	 * Read value from shm segment
	 * @param string key
	 * @return string | null if field doesn't exist
	 */
	protected function readValue($key) {
		$data = $this->readData();
		return isset($data[$key]) ? $data[$key] : null;
	}

	/**
	 * Write data to shm segment
	 * @param array $data
	 */
	protected function writeData(array $data) {
		$size = $this->write(self::SHM_DATA_OFFSET, json_encode($data));
		$this->updateSize($size);
	}

	/**
	 * Read data from shm segment
	 * @param string $key
	 * @return string
	 */
	protected function readData() {
		$used = $this->readSize();
		return 0 === $used ? array() : json_decode($this->read(self::SHM_DATA_OFFSET, $used), true);
	}

	/**
	 * Write data to shm segment
	 * @param int $offset
	 * @param string $data
	 * @return number of written bytes
	 */
	protected function write($offset, $data) {
		return shmop_write($this->shm, $data, $offset);
	}

	/**
	 * Read data from shm segment
	 * @param int $offset start position
	 * @param int $size number of bytes to read
	 * @return string data
	 */
	protected function read($offset, $size) {
		return shmop_read($this->shm, $offset, $size);
	}
}
