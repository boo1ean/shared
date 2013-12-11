<?php namespace Shared\Tests;

use Shared\Storage;

class BasicTest extends TestCase
{
	public function testLock() {
		$key = uniqid();
		$storage = new Storage($key);
		$storage->destroy();
	}
}
