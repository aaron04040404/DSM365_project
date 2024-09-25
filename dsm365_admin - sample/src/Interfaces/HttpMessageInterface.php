<?php
namespace Gn\Interfaces;

// from Slim
use Slim\Http\StatusCode;

/**
 * All http response default message string are collected in this interface for implementing
 * @author nickfeng 2019-08-30
 *
 */
interface HttpMessageInterface {
    const HTTP_MSG_COOKIE_DENIED = 'cookie denied';
    
    // the array contents are clone from Slim
    // all work for HTTP response <--> HTTP code
    const HTTP_RESP_MSG = [
        //Informational 1xx
        StatusCode::HTTP_CONTINUE => 'Continue',
        StatusCode::HTTP_SWITCHING_PROTOCOLS => 'Switching Protocols',
        StatusCode::HTTP_PROCESSING => 'Processing',
        //Successful 2xx
        StatusCode::HTTP_OK => 'OK',
        StatusCode::HTTP_CREATED => 'Created',
        StatusCode::HTTP_ACCEPTED => 'Accepted',
        StatusCode::HTTP_NONAUTHORITATIVE_INFORMATION => 'Non-Authoritative Information',
        StatusCode::HTTP_NO_CONTENT => 'No Content',
        StatusCode::HTTP_RESET_CONTENT => 'Reset Content',
        StatusCode::HTTP_PARTIAL_CONTENT => 'Partial Content',
        StatusCode::HTTP_MULTI_STATUS => 'Multi-Status',
        StatusCode::HTTP_ALREADY_REPORTED => 'Already Reported',
        StatusCode::HTTP_IM_USED => 'IM Used',
        //Redirection 3xx
        StatusCode::HTTP_MULTIPLE_CHOICES => 'Multiple Choices',
        StatusCode::HTTP_MOVED_PERMANENTLY => 'Moved Permanently',
        StatusCode::HTTP_FOUND => 'Found',
        StatusCode::HTTP_SEE_OTHER => 'See Other',
        StatusCode::HTTP_NOT_MODIFIED => 'Not Modified',
        StatusCode::HTTP_USE_PROXY => 'Use Proxy',
        StatusCode::HTTP_UNUSED => '(Unused)',
        StatusCode::HTTP_TEMPORARY_REDIRECT => 'Temporary Redirect',
        StatusCode::HTTP_PERMANENT_REDIRECT => 'Permanent Redirect',
        //Client Error 4xx
        StatusCode::HTTP_BAD_REQUEST => 'Bad Request',
        StatusCode::HTTP_UNAUTHORIZED => 'Unauthorized',
        StatusCode::HTTP_PAYMENT_REQUIRED => 'Payment Required',
        StatusCode::HTTP_FORBIDDEN => 'Forbidden',
        StatusCode::HTTP_NOT_FOUND => 'Not Found',
        StatusCode::HTTP_METHOD_NOT_ALLOWED => 'Method Not Allowed',
        StatusCode::HTTP_NOT_ACCEPTABLE => 'Not Acceptable',
        StatusCode::HTTP_PROXY_AUTHENTICATION_REQUIRED => 'Proxy Authentication Required',
        StatusCode::HTTP_REQUEST_TIMEOUT => 'Request Timeout',
        StatusCode::HTTP_CONFLICT => 'Conflict',
        StatusCode::HTTP_GONE => 'Gone',
        StatusCode::HTTP_LENGTH_REQUIRED => 'Length Required',
        StatusCode::HTTP_PRECONDITION_FAILED => 'Precondition Failed',
        StatusCode::HTTP_REQUEST_ENTITY_TOO_LARGE => 'Request Entity Too Large',
        StatusCode::HTTP_REQUEST_URI_TOO_LONG => 'Request-URI Too Long',
        StatusCode::HTTP_UNSUPPORTED_MEDIA_TYPE => 'Unsupported Media Type',
        StatusCode::HTTP_REQUESTED_RANGE_NOT_SATISFIABLE => 'Requested Range Not Satisfiable',
        StatusCode::HTTP_EXPECTATION_FAILED => 'Expectation Failed',
        StatusCode::HTTP_IM_A_TEAPOT => 'I\'m a teapot',
        StatusCode::HTTP_MISDIRECTED_REQUEST => 'Misdirected Request',
        StatusCode::HTTP_UNPROCESSABLE_ENTITY => 'Unprocessable Entity',
        StatusCode::HTTP_LOCKED => 'Locked',
        StatusCode::HTTP_FAILED_DEPENDENCY => 'Failed Dependency',
        StatusCode::HTTP_UPGRADE_REQUIRED => 'Upgrade Required',
        StatusCode::HTTP_PRECONDITION_REQUIRED => 'Precondition Required',
        StatusCode::HTTP_TOO_MANY_REQUESTS => 'Too Many Requests',
        StatusCode::HTTP_REQUEST_HEADER_FIELDS_TOO_LARGE => 'Request Header Fields Too Large',
        StatusCode::HTTP_CONNECTION_CLOSED_WITHOUT_RESPONSE => 'Connection Closed Without Response',
        StatusCode::HTTP_UNAVAILABLE_FOR_LEGAL_REASONS => 'Unavailable For Legal Reasons',
        StatusCode::HTTP_CLIENT_CLOSED_REQUEST => 'Client Closed Request',
        //Server Error 5xx
        StatusCode::HTTP_INTERNAL_SERVER_ERROR => 'Internal Server Error',
        StatusCode::HTTP_NOT_IMPLEMENTED => 'Not Implemented',
        StatusCode::HTTP_BAD_GATEWAY => 'Bad Gateway',
        StatusCode::HTTP_SERVICE_UNAVAILABLE => 'Service Unavailable',
        StatusCode::HTTP_GATEWAY_TIMEOUT => 'Gateway Timeout',
        StatusCode::HTTP_VERSION_NOT_SUPPORTED => 'HTTP Version Not Supported',
        StatusCode::HTTP_VARIANT_ALSO_NEGOTIATES => 'Variant Also Negotiates',
        StatusCode::HTTP_INSUFFICIENT_STORAGE => 'Insufficient Storage',
        StatusCode::HTTP_LOOP_DETECTED => 'Loop Detected',
        StatusCode::HTTP_NOT_EXTENDED => 'Not Extended',
        StatusCode::HTTP_NETWORK_AUTHENTICATION_REQUIRED => 'Network Authentication Required',
        StatusCode::HTTP_NETWORK_CONNECTION_TIMEOUT_ERROR => 'Network Connect Timeout Error',
    ];
}
