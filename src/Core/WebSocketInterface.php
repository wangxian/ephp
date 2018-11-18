<?php
namespace ePHP\Core;

use Swoole\Http\Request;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server;


/**
 * WebSocketInterface
 */
interface WebSocketInterface
{
    /**
     * @param Server $server
     * @param Request $request
     * @return void
     */
    public function onOpen(Server $server, Request $request);

    /**
     * @param Server $server
     * @param Frame $frame
     * @return void
     */
    public function onMessage(Server $server, Frame $frame);

    /**
     * on connection closed
     * - you can do something. eg. record log
     * @param Server $server
     * @param int $fd
     * @return void
     */
    public function onClose(Server $server, int $fd);
}
