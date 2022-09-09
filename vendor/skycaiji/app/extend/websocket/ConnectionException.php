<?php
/*
 |--------------------------------------------------------------------------
 | SkyCaiji (蓝天采集器)
 |--------------------------------------------------------------------------
 | Copyright (c) 2018 https://www.skycaiji.com All rights reserved.
 |--------------------------------------------------------------------------
 | 使用协议  https://www.skycaiji.com/licenses
 |--------------------------------------------------------------------------
 */


namespace WebSocket;

class ConnectionException extends Exception
{
    
    const TIMED_OUT = 1024;
    const EOF = 1025;
    const BAD_OPCODE = 1026;
}
