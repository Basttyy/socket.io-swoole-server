<?php

declare(strict_types=1);

namespace SocketIO\Storage\Table;

use Exception;
use Swoole\Table;

/**
 * Class RoomTable
 *
 * eg. room => [ 'fds' => '[1,2,3]']
 *
 * @package SocketIO\Storage\Table
 */
class RoomTable extends BaseTable
{
    /** @var RoomTable */
    private static $instance = null;

    private function __construct(){}

    /**
     * @param int $row
     * @param int $size
     *
     * @return RoomTable
     */
    public static function getInstance(int $row = 1000, int $size = 64 * 1000)
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();

            self::$instance->initTable($row, $size);
        }

        return self::$instance;
    }

    /**
     * @param int $row
     * @param int $size
     */
    private function initTable(int $row, int $size)
    {
        $this->tableKey = 'fds';

        $this->table = new Table($row);
        $this->table->column($this->tableKey, Table::TYPE_STRING, $size);
        $this->table->create();
    }

    /**
     * @param string $room
     * @param string $fd
     *
     * @return bool
     *
     * @throws \Exception
     */
    public function push(string $room, int $fd) : bool
    {
        if ($this->table->exist($room)) {
            $value = $this->table->get($room, $this->tableKey);
            if ($value) {
                $value = \json_decode($value, true);
                if (\is_null($value)) {
                    throw new Exception('json decode failed: ' . \json_last_error_msg());
                }
                if (\in_array($fd, $value)) {
                    return true;
                } else {
                    \array_push($value, $fd);
                    $value = [
                        $this->tableKey => \json_encode($value)
                    ];

                    return $this->setTable($room, $value);
                }
            } else {
                throw new Exception('get table key return false');
            }
        } else {
            $value = [
                $this->tableKey => \json_encode($fd)
            ];

            return $this->setTable($room, $value);
        }
    }

    /**
     * @param string $room
     * @param string $fd
     * @return bool
     * @throws \Exception
     */
    public function pop(string $room, int $fd) : bool
    {
        if ($this->table->exist($room)) {
            $value = $this->table->get($room, $this->tableKey);
            if ($value) {
                $value = \json_decode($value, true);
                if (\is_null($value)) {
                    throw new Exception('json decode failed: ' . \json_last_error_msg());
                }
                if (!\in_array($fd, $value)) {
                    return true;
                } else {
                    $value = \array_diff($value, [$fd]);
                    $value = [
                        $this->tableKey = \json_encode($value)
                    ];

                    return $this->setTable($room, $value);
                }
            } else {
                throw new \Exception('get table key return false');
            }
        } else {
            return true;
        }
    }

    /**
     * @param string $room
     * @return bool
     */
    public function destroy(string $room) : bool
    {
        return $this->table->del($room);
    }

    /**
     * @param string $room
     *
     * @return string
     *
     * @throws \Exception
     */
    public function get(string $room) : string
    {
        $value = $this->table->get($room, $this->tableKey);
        if ($value !== false) {
            return $value;
        } else {
            throw new \Exception('get table key return false');
        }
    }

        /**
     * @param string $room
     *
     * @return array
     *
     * @throws \Exception
     */
    public function getFds(string $room) : array
    {
        $value = $this->table->get($room, $this->tableKey);
        if ($value) {
            $value = \json_decode($value, true);
            if (is_null($value)) {
                throw new \Exception('json decode failed: ' . json_last_error_msg());
            }
            return $value;
        } else {
            throw new \Exception('get table key return false');
        }
    }
}