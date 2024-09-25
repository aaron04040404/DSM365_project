<?php
namespace Gn\Interfaces;

/**
 * All http response default message string are collected in this interface for implementing
 * @author nickfeng 2019-08-30
 *
 */
interface DisplayNetworkInterface {
    // 2 is admin level, if over than it, system has to detect the display is in the network of a user.
    const DETECT_THRESHOLD = 2;
}
