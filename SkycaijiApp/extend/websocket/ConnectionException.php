<?php

namespace WebSocket;

class ConnectionException extends Exception
{
    // Native codes in interval 0-106
    const TIMED_OUT = 1024;
    const EOF = 1025;
    const BAD_OPCODE = 1026;
}
