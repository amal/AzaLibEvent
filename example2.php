<?php

use Aza\Components\Cli\Base;
use Aza\Components\LibEvent\EventBase;
use Aza\Components\LibEvent\EventBuffer;

require __DIR__ . '/Components/Cli/Base.php';
require __DIR__ . '/Components/LibEvent/Exceptions/Exception.php';
require __DIR__ . '/Components/LibEvent/EventBase.php';
require __DIR__ . '/Components/LibEvent/EventBasic.php';
require __DIR__ . '/Components/LibEvent/EventBuffer.php';


/**
 * AzaLibEvent Example #2 - polling STDIN using buffered event API
 *
 * @link http://www.php.net/manual/en/libevent.examples.php
 *
 * @project Anizoptera CMF
 * @package system.AzaLibEvent
 * @author  Amal Samally <amal.samally at gmail.com>
 * @license MIT
 */


/**
 * Callback to invoke where there is data to read
 *
 * @param resource $buf  File descriptor
 * @param array    $args Event arguments - array(EventBuffer $e, mixed $arg)
 */
function print_line($buf, $args)
{
    static $max_requests;
    $max_requests++;

	/**
	 * @var $e    EventBuffer
	 * @var $base EventBase
	 */
	list($e, $base) = $args;

    // exit loop after 10 writes
    if ($max_requests == 10) {
		$base->loopExit();
    }

    // print the line
    echo $e->read(4096);
}

/**
 * Callback to invoke where there is an error on the descriptor.
 * function(resource $buf, int $what, array $args(EventBuffer $e, mixed $arg))
 *
 * @param resource $buf  File descriptor
 * @param int      $what What kind of error occurred. See EventBuffer::E_* constants
 * @param array    $args Event arguments - array(EventBuffer $e, mixed $arg)
 */
function error_func($buf, $what, $args) {}


// I use Base::getEventBase() to operate always with the
// same instance, but you can simply use "new EventBase()"

// Get event base
$base = Base::getEventBase();

// Create buffered event
$ev = new EventBuffer(STDIN, 'print_line', null, 'error_func', $base);
$ev->setBase($base)->enable(EV_READ);

// Start loop
$base->loop();
