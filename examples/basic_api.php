<?php

use Aza\Components\LibEvent\EventBase;
use Aza\Components\LibEvent\Event;

require __DIR__ . '/../vendor/autoload.php';

/**
 * Example #1 - polling STDIN using basic API
 *
 * @link http://www.php.net/manual/en/libevent.examples.php
 *
 * @project Anizoptera CMF
 * @package system.libevent
 * @author  Amal Samally <amal.samally at gmail.com>
 * @license MIT
 */

/**
 * Callback function to be called when the matching event occurs
 *
 * @param resource $fd     File descriptor
 * @param int      $events What kind of events occurred. See EV_* constants
 * @param array    $args   Event arguments - array(Event $e, mixed $arg)
 */
function print_line($fd, $events, $args)
{
	static $max_requests = 0;
	$max_requests++;

	/**
	 * @var $e    Event
	 * @var $base EventBase
	 */
	list($e, $base) = $args;

	// exit loop after 10 writes
	if ($max_requests == 10) {
		$base->loopExit();
	}

	// print the line
	echo fgets($fd);
}

// Create base
$base = new EventBase;

// Setup and enable event
$ev = new Event();
$ev->set(STDIN, EV_READ|EV_PERSIST, 'print_line', $base)
		->setBase($base)
		->add();

// Start event loop
$base->loop();
