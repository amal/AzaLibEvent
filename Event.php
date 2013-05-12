<?php

namespace Aza\Components\LibEvent;
use Aza\Components\LibEvent\Exceptions\Exception;
use Aza\Components\CliBase\Base;

/**
 * LibEvent event resource wrapper
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
class Event extends EventBasic
{
	/**
	 * {@inheritdoc}
	 *
	 * @see event_new
	 */
	public function __construct()
	{
		parent::__construct();
		if (!$this->resource = event_new()) {
			throw new Exception(
				"Can't create new event resource (event_new)"
			);
		}
	}


	/**
	 * Adds an event to the set of monitored events.
	 *
	 * @see event_add
	 *
	 * @throws Exception if can't add event
	 *
	 * @param int $timeout Optional timeout (in microseconds).
	 *
	 * @return $this
	 */
	public function add($timeout = -1)
	{
		// Save one method call (checkResource)
		($resource = $this->resource) || $this->checkResource();

		if (!event_add($resource, $timeout)) {
			throw new Exception(
				"Can't add event (event_add)"
			);
		}
		return $this;
	}

	/**
	 * Remove an event from the set of monitored events.
	 *
	 * @see event_del
	 *
	 * @throws Exception if can't delete event
	 *
	 * @return $this
	 */
	public function del()
	{
		// Save one method call (checkResource)
		($resource = $this->resource) || $this->checkResource();

		if (!event_del($resource)) {
			throw new Exception(
				"Can't delete event (event_del)"
			);
		}
		return $this;
	}


	/**
	 * {@inheritdoc}
	 *
	 * @see event_base_set
	 *
	 * @throws Exception
	 */
	public function setBase($event_base)
	{
		$this->checkResource();
		$event_base->checkResource();

		if (!event_base_set(
			$this->resource,
			$event_base->resource
		)) {
			throw new Exception(
				"Can't set event base (event_base_set)"
			);
		}

		return parent::setBase($event_base);
	}

	/**
	 * {@inheritdoc}
	 *
	 * @see event_free
	 */
	public function free($afterForkCleanup = false)
	{
		parent::free();
		if ($resource = $this->resource) {
			event_del($resource);
			event_free($resource);
			$this->resource = null;
		}
		return $this;
	}


	/**
	 * Prepares the event to be used in add().
	 *
	 * @see add
	 * @see event_add
	 * @see event_set
	 *
	 * @throws Exception if can't prepare event
	 *
	 * @param resource|mixed $fd <p>
	 * Valid PHP stream resource. The stream must be
	 * castable to file descriptor, so you most likely
	 * won't be able to use any of filtered streams.
	 * </p>
	 * @param int $events <p>
	 * A set of flags indicating the desired event,
	 * can be EV_TIMEOUT, EV_READ, EV_WRITE
	 * and EV_SIGNAL.
	 * The additional flag EV_PERSIST makes the event
	 * to persist until {@link event_del}() is called,
	 * otherwise the callback is invoked only once.
	 * </p>
	 * @param callback $callback <p>
	 * Callback function to be called when the matching
	 * event occurs.
	 * <br><tt>function(resource|null $fd, int $events,
	 * array $arg(Event $event, mixed $arg)){}</tt>
	 * </p>
	 * @param mixed $arg
	 *
	 * @return $this
	 */
	public function set($fd, $events, $callback, $arg = null)
	{
		$this->checkResource();

		if (!event_set(
			$this->resource,
			$fd,
			$events,
			$callback,
			array($this, $arg)
		)) {
			throw new Exception(
				"Can't prepare event (event_set)"
			);
		}

		return $this;
	}

	/**
	 * Prepares the event to be used in add() as signal handler.
	 *
	 * @see set
	 * @see add
	 * @see event_add
	 * @see event_set
	 *
	 * @throws Exception if can't prepare event
	 *
	 * @param int $signo <p>
	 * Signal number
	 * </p>
	 * @param callback $callback <p>
	 * Callback function to be called when the matching event occurs.
	 * <br><tt>function(null $fd, int $events(8:EV_SIGNAL),
	 * array $arg(Event $event, mixed $arg, int $signo)){}</tt>
	 * </p>
	 * @param bool $persist <p>
	 * Whether the event will persist until {@link event_del}() is
	 * called, otherwise the callback is invoked only once.
	 * </p>
	 * @param mixed $arg
	 *
	 * @return $this
	 */
	public function setSignal($signo, $callback,
		$persist = true, $arg = null)
	{
		// Save one method call (checkResource)
		($resource = $this->resource) || $this->checkResource();

		$events = EV_SIGNAL;

		$persist
			&& $events |= EV_PERSIST;

		if (!event_set(
			$resource,
			$signo,
			$events,
			$callback,
			array($this, $arg, $signo)
		)) {
			$name = Base::getSignalName($signo);
			throw new Exception(
				"Can't prepare event (event_set) for $name ($signo) signal"
			);
		}
		return $this;
	}

	/**
	 * Prepares the timer event.
	 * Use {@link add}() in callback again with
	 * interval to repeat timer.
	 *
	 * @see event_timer_set
	 *
	 * @throws Exception if can't prepare event
	 *
	 * @param callback $callback <p>
	 * Callback function to be called when the interval expires.
	 * <br><tt>function(null $fd, int $events(1:EV_TIMEOUT),
	 * array $arg(Event $event, mixed $arg)){}</tt>
	 * </p>
	 * @param mixed $arg
	 *
	 * @return $this
	 */
	public function setTimer($callback, $arg = null)
	{
		// Save one method call (checkResource)
		($resource = $this->resource) || $this->checkResource();

		if (!event_timer_set(
			$resource,
			$callback,
			array($this, $arg)
		)) {
			throw new Exception(
				"Can't prepare event (event_timer_set) for timer"
			);
		}
		return $this;
	}
}
