<?php

namespace DurbaRoy\Zkteco\Protocol;

/**
 * ZKTeco lower-level command IDs (UDP 4370).
 * Values based on open ZK protocol docs.
 */
final class Commands
{
    // General device control
    public const CMD_CONNECT       = 1000;
    public const CMD_EXIT          = 1001;
    public const CMD_ENABLEDEVICE  = 1002;
    public const CMD_DISABLEDEVICE = 1003;
    public const CMD_RESTART       = 1004;
    public const CMD_POWEROFF      = 1005;

    // Data transfer & ack
    public const CMD_ACK_OK        = 2000;
    public const CMD_ACK_ERROR     = 2001;
    public const CMD_ACK_DATA      = 2002;
    public const CMD_PREPARE_DATA  = 1500;
    public const CMD_DATA          = 1501;
    public const CMD_FREE_DATA     = 1502;

    // User / template / attendance
    public const CMD_SET_USER      = 8;   // write user
    public const CMD_USERTEMP_RRQ  = 9;   // request user template
    public const CMD_ATTLOG_RRQ    = 13;  // request attendance logs
    public const CMD_CLEAR_DATA    = 14;  // clear all data
    public const CMD_CLEAR_ATTLOG  = 15;  // clear attendance logs

    // Time
    public const CMD_GET_TIME      = 201;
    public const CMD_SET_TIME      = 202;

    // Internal sizes (from docs)
    public const USER_DATA_SIZE        = 72;
    public const ATTENDANCE_DATA_SIZE  = 40;
}
