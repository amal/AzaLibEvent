<?php

namespace Aza\Components\LibEvent;
use Aza\Components\LibEvent\Exceptions\Exception;
use Aza\Components\CliBase\Base;

/**
 * LibEvent base resource wrapper (event loop)
 *
 * @link http://www.wangafu.net/~nickm/libevent-book/
 *
 * @uses libevent
 *
 * @project Anizoptera CMF
 * @package system.libevent
 * @author  Amal Samally <amal.samally at gmail.com>
 * @license MIT
 */
class EventBase
{
	/**
	 * Default priority
	 *
	 * @see priorityInit
	 */
	const MAX_PRIORITY = 30;


	/**
	 * Unique base IDs counter
	 *
	 * @var int
	 */
	private static $counter = 0;

	/**
	 * Unique base ID
	 *
	 * @var int
	 */
	public $id;

	/**
	 * Event base resource
	 *
	 * @var resource
	 */
	public $resource;

	/**
	 * Events
	 *
	 * @var EventBasic[]
	 */
	public $events = array();

	/**
	 * Array of timers settings
	 *
	 * @var array[]
	 */
	protected $timers = array();



	/**
	 * Initializes instance
	 *
	 * @see event_base_new
	 *
	 * @throws Exception
	 *
	 * @param bool $initPriority Whether to init
	 * priority with default value
	 */
	public function __construct($initPriority = true)
	{
		$this->init($initPriority);
	}

	/**
	 * Create and initialize new event base
	 *
	 * @see event_base_new
	 *
	 * @throws Exception
	 *
	 * @param bool $initPriority Whether to init priority with default value
	 *
	 * @return $this
	 */
	protected function init($initPriority = true)
	{
		if (!Base::$hasLibevent) {
			throw new Exception(
				'You need to install PECL extension "Libevent" to use this class'
			);
		} else if (!$this->resource = event_base_new()) {
			throw new Exception(
				"Can't create event base resource (event_base_new)"
			);
		}
		$initPriority && $this->priorityInit();
		$this->id = ++self::$counter;
		return $this;
	}


	/**
	 * Prepares event base for forking
	 * to avoid resources damage
	 *
	 * Use this method before fork in parent!
	 */
	public function beforeFork()
	{
		if ($this->events) {
			// Trigger already ready events
			$this->loop(EVLOOP_NONBLOCK);

			// Disable buffered events
			foreach ($this->events as $e) {
				if ($e instanceof EventBuffer) {
					$e->disable(0);
				}
			}
		}
	}

	/**
	 * Enables all disabled events
	 *
	 * Use this method after fork in parent!
	 */
	public function afterFork()
	{
		// Enable buffered events
		if ($this->events) {
			foreach ($this->events as $e) {
				if ($e instanceof EventBuffer) {
					$e->enable(0);
				}
			}
		}
	}

	/**
	 * Cleans all attached events and reinitializes
	 * event base.
	 *
	 * Use this method after fork in child!
	 *
	 * @param bool $initPriority
	 * Whether to init priority with default value
	 *
	 * @return $this
	 */
	public function reinitialize($initPriority = true)
	{
		return $this->free(true)->init($initPriority);
	}


	/**
	 * Desctructor
	 */
	public function __destruct()
	{
		$this->free();
	}

	/**
	 * Destroys the specified event_base and frees
	 * all the resources associated.
	 *
	 * Note that it's not possible to destroy an
	 * event base with events attached to it.
	 *
	 * @see event_base_free
	 *
	 * @param bool $afterForkCleanup [optional] <p>
	 * Special handling of cleanup after fork
	 * </p>
	 *
	 * @return $this
	 */
	public function free($afterForkCleanup = false)
	{
		$this->freeAttachedEvents($afterForkCleanup);
		if ($this->resource) {
			@event_base_free($this->resource);
			$this->resource = null;
		}
		return $this;
	}

	/**
	 * Frees all attached timers and events
	 *
	 * @param bool $afterForkCleanup [optional] <p>
	 * Special handling of cleanup after fork
	 * </p>
	 *
	 * @return $this
	 */
	public function freeAttachedEvents($afterForkCleanup = false)
	{
		if ($this->events) {
			foreach ($this->events as $e) {
				$e->free($afterForkCleanup);
			}
		}
		$this->events = $this->timers = array();
		return $this;
	}


	/**
	 * Associate event base with an event (or buffered event).
	 *
	 * @see Event::setBase
	 * @see EventBuffer::setBase
	 *
	 * @throws Exception
	 *
	 * @param EventBasic $event
	 *
	 * @return $this
	 */
	public function setEvent($event)
	{
		$event->setBase($this);
		return $this;
	}


	/**
	 * Starts event loop for the specified event base.
	 *
	 * @see event_base_loop
	 *
	 * @throws Exception if error
	 *
	 * @param int $flags [optional] <p>
	 * Any combination of EVLOOP_ONCE
	 * and EVLOOP_NONBLOCK.
	 * <p>
	 *
	 * @return int Returns 0 on success, 1 if no events were registered.
	 */
	public function loop($flags = 0)
	{
		// Save one method call (checkResource)
		($resource = $this->resource) || $this->checkResource();

		$res = event_base_loop($resource, $flags);
		if ($res === -1) {
			throw new Exception(
				"Can't start base loop (event_base_loop)"
			);
		}
		return $res;
	}

	/**
	 * Abort the active event loop immediately.
	 * The behaviour is similar to break statement.
	 *
	 * @see event_base_loopbreak
	 *
	 * @throws Exception
	 *
	 * @return $this
	 */
	public function loopBreak()
	{
		// Save one method call (checkResource)
		($resource = $this->resource) || $this->checkResource();

		if (!event_base_loopbreak($resource)) {
			throw new Exception(
				"Can't break loop (event_base_loopbreak)"
			);
		}
		return $this;
	}

	/**
	 * Exit loop after a time.
	 *
	 * @see event_base_loopexit
	 *
	 * @throws Exception
	 *
	 * @param int $timeout [optional] <p>
	 * Timeout in microseconds.
	 * <p>
	 *
	 * @return $this
	 */
	public function loopExit($timeout = -1)
	{
		// Save one method call (checkResource)
		($resource = $this->resource) || $this->checkResource();

		if (!event_base_loopexit($resource, $timeout)) {
			throw new Exception(
				"Can't set loop exit timeout (event_base_loopexit)"
			);
		}
		return $this;
	}


	/**
	 * Sets the maximum priority level of the event base.
	 *
	 * @see event_base_priority_init
	 *
	 * @throws Exception
	 *
	 * @param int $value
	 *
	 * @return $this
	 */
	public function priorityInit($value = self::MAX_PRIORITY)
	{
		$this->checkResource();
		if (!event_base_priority_init($this->resource, ++$value)) {
			throw new Exception(
				"Can't set the maximum priority level of the event base"
				." to {$value} (event_base_priority_init)"
			);
		}
		return $this;
	}


	/**
	 * Checks event base resource.
	 *
	 * @throws Exception if resource is already freed
	 */
	public function checkResource()
	{
		if (!$this->resource) {
			throw new Exception(
				"Can't use event base resource. It's already freed."
			);
		}
	}


	/**
	 * Adds a new named timer to the base or customize existing
	 *
	 * @param string $name Timer name
	 * @param int  $interval Interval
	 * @param callback $callback <p>
	 * Callback function to be called when the interval expires.<br/>
	 * <tt>function(string $timer_name, mixed $arg,
	 * int $iteration, EventBase $event_base){}</tt><br/>
	 * If callback will return TRUE timer will be started
	 * again for next iteration.
	 * </p>
	 * @param mixed $arg   Additional timer argument
	 * @param bool  $start Whether to start timer
	 * @param int   $q     Interval multiply factor
	 *
	 * @throws Exception
	 */
	public function timerAdd($name, $interval = null, $callback = null,
		$arg = null, $start = true, $q = 1000000)
	{
		$notExists = !isset($this->timers[$name]);

		if (($notExists || $callback)
		    && !is_callable($callback, false, $callableName)
		) {
			throw new Exception(
				"Incorrect callback '{$callableName}' for timer ({$name})."
			);
		}

		if ($notExists) {
			$event = new Event();
			$event->setTimer(array($this, '_onTimer'), $name)
					->setBase($this);

			$this->timers[$name] = array(
				'name'     => $name,
				'callback' => $callback,
				'event'    => $event,
				'interval' => $interval,
				'arg'      => $arg,
				'q'        => $q,
				'i'        => 0,
			);
		} else {
			/** @var $timer Event[]|mixed[] */
			$timer = &$this->timers[$name];
			$timer['event']->del();

			$callback
				&& $timer['callback'] = $callback;

			$interval > 0
				&& $timer['interval'] = $interval;

			isset($arg)
				&& $timer['arg'] = $arg;

			$timer['i'] = 0;
		}

		if ($start) {
			$this->timerStart($name);
		}
	}

	/**
	 * Starts timer
	 *
	 * @param string $name           Timer name
	 * @param int    $interval       Interval
	 * @param mixed  $arg            Additional timer argument
	 * @param bool   $resetIteration Whether to reset iteration counter
	 *
	 * @throws Exception
	 */
	public function timerStart($name, $interval = null,
		$arg = null, $resetIteration = true)
	{
		if (!isset($this->timers[$name])) {
			throw new Exception(
				"Unknown timer '{$name}'. Add timer before using."
			);
		}

		/** @var $timer Event[]|mixed[] */
		$timer = &$this->timers[$name];

		$resetIteration
			&& $timer['i'] = 0;

		isset($arg)
			&& $timer['arg'] = $arg;

		$interval > 0
			&& $timer['interval'] = $interval;

		$timer['event']->add((int)($timer['interval'] * $timer['q']));
	}

	/**
	 * <p>Stops timer when it's started and waiting.</p>
	 *
	 * <p>Don't call from timer callback.
	 * Return FALSE instead - see {@link timerAdd}().</p>
	 *
	 * @param string $name Timer name
	 */
	public function timerStop($name)
	{
		if (!isset($this->timers[$name])) {
			return;
		}

		/** @var $timer Event[]|mixed[] */
		$timer = &$this->timers[$name];
		$timer['event']->del();
		$timer['i'] = 0;
	}

	/**
	 * Completely destroys timer
	 *
	 * @param string $name Timer name
	 */
	public function timerDelete($name)
	{
		if (!isset($this->timers[$name])) {
			return;
		}
		/** @var $timer Event[] */
		$timer = $this->timers[$name];
		$timer['event']->free();
		unset($this->timers[$name]);
	}

	/**
	 * Return whther timer with such name exists in the base
	 *
	 * @param string $name Timer name
	 *
	 * @return bool
	 */
	public function timerExists($name)
	{
		return isset($this->timers[$name]);
	}

	/**
	 * Timer callback
	 *
	 * @see Event::setTimer
	 *
	 * @access protected
	 * @internal
	 *
	 * @param null  $fd
	 * @param int   $event EV_TIMEOUT
	 * @param array $args
	 */
	public function _onTimer($fd, $event, $args)
	{
		// Skip deleted timers
		if (!isset($this->timers[$name = $args[1]])) {
			return;
		}

		// Invoke callback
		$timer = &$this->timers[$name];
		if (call_user_func(
			$timer['callback'],
			$name,
			$timer['arg'],
			++$timer['i'],
			$this,
			$event,
			$fd
		)) {
			$this->timerStart(
				$name, null, null, false
			);
		} else {
			$timer['i'] = 0;
		}
	}
}
