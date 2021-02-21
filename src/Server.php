<?php

declare(strict_types=1);

namespace SocketIO;

use Exception;
use Swoole\Coroutine\Channel;
use SocketIO\Engine\Payload\ChannelPayload;
use SocketIO\Engine\Payload\ConfigPayload;
use SocketIO\Engine\Server as EngineServer;
use SocketIO\Enum\Message\PacketTypeEnum;
use SocketIO\Enum\Message\TypeEnum;
use SocketIO\Event\EventPayload;
use SocketIO\Event\EventPool;
use SocketIO\Storage\Table\ListenerSessionTable;
use SocketIO\Storage\Table\ListenerTable;
use SocketIO\Parser\WebSocket\Packet;
use SocketIO\Parser\WebSocket\PacketPayload;
use SocketIO\Storage\Table\NamespaceSessionTable;
use SocketIO\Storage\Table\SessionListenerTable;
use Swoole\WebSocket\Server as WebSocketServer;
use SocketIO\ExceptionHandler\InvalidEventException;
use SocketIO\Storage\Table\RoomTable;

/**
 * Class Server
 *
 * @package SocketIO
 */
class Server
{
    /** @var Callable */
    private $callback;

    /** @var string */
    private $namespace = '/';

    /** @var WebSocketServer */
    private $webSocketServer;

    /** socket sender's fd @var int */
    private $sender;

    /** @var string */
    private $message;

    /** @var string */
    private $isBroadcast;

    /** @var array */
    private $to;

    /** @var int */
    private $port;

    /** @var ConfigPayload */
    private $configPayload;

    public function __construct(int $port, ConfigPayload $configPayload, Callable $callback)
    {
        $this->port = $port;

        $this->configPayload = $configPayload;

        $this->callback = $callback;

        $this->isBroadcast = false;

        $this->to = [];
    }

    /**
     * @return WebSocketServer
     */
    public function getWebSocketServer(): WebSocketServer
    {
        return $this->webSocketServer;
    }

    /**
     * @param WebSocketServer $webSocketServer
     *
     * @return Server
     */
    public function setWebSocketServer(WebSocketServer $webSocketServer): self
    {
        $this->webSocketServer = $webSocketServer;

        return $this;
    }

    /**
     * Get current sender fd.
     * @return int
     */
    public function getSender(): int
    {
        return $this->sender;
    }

    /**
     * Set sender fd.
     * 
     * @param int $fd
     * @return Server
     */
    public function setSender(int $fd): Server
    {
        $this->sender = $fd;
        return $this;
    }

    /**
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @param string $message
     *
     * @return Server
     */
    public function setMessage(string $message): self
    {
        $this->message = $message;

        return $this;
    }

    public function of(string $namespace): self
    {
        $this->namespace = !empty($namespace) ? $namespace : $this->namespace;

        return $this;
    }

    /**
     * @throws \Exception
     */
    public function broadcast() : self
    {
        $this->isBroadcast = true;

        return $this;
    }

    /**
     * @param string $eventName
     * @param callable $callback
     *
     * @return Server
     *
     * @throws InvalidEventException
     */
    public function on(string $eventName, callable $callback) : self
    {
        if (empty($eventName) || !is_callable($callback)) {
            throw new InvalidEventException('invalid Event');
        }

        $this->consumeEvent($eventName, $callback);

        return $this;
    }

    /**
     * @param string|array $values
     * 
     * @return bool
     * 
     * @throws Exception
     */
    public function to(string|int|array $values): self
    {
        $values = \is_integer($values) || \is_string($values) ? \func_get_args() : $values;

        foreach ($values as $value) {
            if (! \in_array($value, $this->to)) {
                $this->to[] = $value;
            }
        }

        return $this;
    }

    /**
     * @param string|array $rooms
     * 
     * @return bool
     * 
     * @throws Exception
     */
    public function join(string|array $rooms) : bool
    {
        if (\gettype($rooms) === "string") {
            return RoomTable::getInstance()->push($rooms, $this->sender);
        } else {
            foreach ($rooms as $room) {
                return RoomTable::getInstance()->push($room, $this->sender);
            }
        }
    }

    /**
     * @param string|array $rooms
     * 
     * @return bool
     * 
     * @throws Exception
     */
    public function leave(string|array $rooms) : bool
    {
        if (\gettype($rooms) === "string") {
            return RoomTable::getInstance()->pop($rooms, $this->sender);
        } else {
            foreach ($rooms as $room) {
                return RoomTable::getInstance()->pop($room, $this->sender);
            }
        }
    }

    /**
     * @param string $eventName
     * @param array $data
     */
    public function emit(string $eventName, array $data) : void
    {
        $packetPayload = new PacketPayload();
        $packetPayload
            ->setNamespace($this->namespace)
            ->setEvent($eventName)
            ->setType(TypeEnum::MESSAGE)
            ->setPacketType(PacketTypeEnum::EVENT)
            ->setMessage(json_encode($data));

        if (!$this->isBroadcast && empty($this->to)) {
            $this->webSocketServer->push($this->sender, Packet::encode($packetPayload));
            return ;
        }

        $this->isBroadcast = false;
    }

    /**
     * Reset some data status
     * 
     * @param bool $force
     * 
     * @return $this
     */
    public function reset(bool $force = false): self
    {
        $this->isBroadcast = false;
        $this->to = [];

        if ($force) {
            $this->sender = null;
            //$this->userId = null;
        }
        return $this;
    }

    public function start()
    {
        $this->initTables();

        new EngineServer($this->port, $this->configPayload, $this->callback, $this);
    }

    /**
     * Get all fds we're going to push data to
     */
    protected function getFds(): array
    {
        $fds = [];
        if (!empty($this->to)) {
            $fds = \array_filter($this->to, function($value) {
                return \is_integer($value);
            });
            $rooms = \array_diff($this->to, $fds);

            if ($this->isBroadcast) {
                $fds[] = $this->sender;
            }
    
            foreach ($rooms as $room) {
                $clients = RoomTable::getInstance()->getFds($room);
                //fallback fd with wrong type back to fds array
                if (empty($clients) && \is_numeric($room)) {
                    $fds[] = $room;
                } else {
                    $fds[] = \array_merge($fds, $clients);
                }
            }
            
            return \array_values(\array_unique($fds));
        }

        $sids = NamespaceSessionTable::getInstance()->get($this->namespace);

        if (!empty($sids)) {
            $sidMapFd = SessionListenerTable::getInstance()->transformSessionToListener($sids);
            if (!empty($sidMapFd)) {
                foreach ($sidMapFd as $sid => $fd) {
                    if (isset($sidMapFd[$sid])) {
                        $fds[] = $fd;
                    } else {
                        // remove sid from NamespaceSessionTable
                        NamespaceSessionTable::getInstance()->pop($this->namespace, $sid);
                    }
                }
            } else {
                echo "broadcast failed, transform Sid to fd return empty\n";
            }
        } else {
            echo "broadcast failed, this namespace has not sid\n";
        }
        return \array_values($fds);
    }
    
    private function consumeEvent(string $eventName, callable $callback)
    {
        if (!EventPool::getInstance()->isExist($this->namespace, $eventName)) {
            $chan = new Channel();
            $eventPayload = new EventPayload();
            $eventPayload
                ->setNamespace($this->namespace)
                ->setName($eventName)
                ->setChan($chan);

            EventPool::getInstance()->push($eventPayload);

            go(function () use ($chan, $eventName, $callback) {
                /** @var ChannelPayload $channelPayload */
                while($channelPayload = $chan->pop()) {
                    $webSocketServer = $channelPayload->getWebSocketServer() ?? null;
                    $message = $channelPayload->getMessage() ?? '';
                    $fd = $channelPayload->getFd() ?? 0;

                    $this->setWebSocketServer($webSocketServer);
                    $this->setMessage($message);
                    if ($fd != 0) {
                        $this->setFd($fd);
                    }

                    $callback($this);
                }
            });
        }

        return;
    }

    private function initTables()
    {
        NamespaceSessionTable::getInstance();
        ListenerSessionTable::getInstance();
        SessionListenerTable::getInstance();
        ListenerTable::getInstance();
        RoomTable::getInstance();
    }
}