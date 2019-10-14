<?php

declare(strict_types=1);

namespace SocketIO\Event;

use SocketIO\Server;

/**
 * Class EventPayload
 *
 * @package SocketIO\Event
 */
class EventPayload
{
    /** @var string */
    private $namespace;

    /** @var string */
    private $name;

    /** @var array */
    private $listeners;

    /** @var callable */
    private $callback;

    /** @var Server */
    private $socket;

    /**
     * @return string
     */
    public function getNamespace(): string
    {
        return $this->namespace;
    }

    /**
     * @param string $namespace
     * @return EventPayload
     */
    public function setNamespace(string $namespace): self
    {
        $this->namespace = $namespace;
        return $this;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     *
     * @return EventPayload
     */
    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return array
     */
    public function getListeners(): array
    {
        return $this->listeners;
    }

    /**
     * @param array $listeners
     *
     * @return EventPayload
     */
    public function setListeners(array $listeners): self
    {
        $this->listeners = $listeners;

        return $this;
    }

    /**
     * @param int $fd
     *
     * @return bool
     */
    public function pushListener(int $fd) : bool
    {
        if (!in_array($fd, $this->listeners)) {
            array_push($this->listeners, $fd);
        }

        return true;
    }

    /**
     * @param int $fd
     *
     * @return bool
     */
    public function popListener(int $fd) : bool
    {
        if (in_array($fd, $this->listeners)) {
            $this->listeners = array_diff($this->listeners, [ $fd ]);
        }

        return true;
    }

    /**
     * @return callable
     */
    public function getCallback(): callable
    {
        return $this->callback;
    }

    /**
     * @param callable $callback
     *
     * @return EventPayload
     */
    public function setCallback(callable $callback): self
    {
        $this->callback = $callback;

        return $this;
    }

    /**
     * @return Server
     */
    public function getSocket(): Server
    {
        return $this->socket;
    }

    /**
     * @param Server $socket
     * @return EventPayload
     */
    public function setSocket(Server $socket): self
    {
        $this->socket = $socket;
        return $this;
    }
}