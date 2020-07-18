<?php /** @noinspection SpellCheckingInspection */

namespace ePHP\Http;

/**
 * Class HttpclientResponse
 * @package ePHP\Http
 */
class HttpclientResponse
{
    /**
     * Raw http curl response
     * @var array
     */
    public $curl_info;

    /**
     * Content Type
     * @var String
     */
    public $content_type;

    /**
     * Http status code
     * @var int
     */
    public $status_code;

    /**
     * Http response body
     * @var string
     */
    public $body;

    /**
     * Response headers
     * @var array
     */
    public $headers;

    /**
     * HttpclientResponse constructor.
     *
     * @param string $body
     * @param int $status_code
     * @param $headers
     * @param string $content_type
     * @param array $curl_info
     */
    public function __construct($body, $status_code, $headers, $content_type = '', $curl_info = array())
    {
        $this->body         = $body;
        $this->status_code  = $status_code;
        $this->headers      = $headers;
        $this->content_type = $content_type;
        $this->curl_info    = $curl_info;
    }
}
