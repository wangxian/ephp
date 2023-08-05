<?php /** @noinspection PhpFullyQualifiedNameUsageInspection */
/** @noinspection SpellCheckingInspection */

/** @noinspection PhpUnused */

namespace ePHP\Http;

/**
 * Make a Http Request using swoole
 *
 * 修改至 https://github.com/dsyph3r/curl-php/blob/master/lib/Network/Curl/Curl.php
 */
class HttpclientSwoole extends Httpclient
{
    /**
     * Create the cURL resource.
     * @noinspection PhpMissingParentConstructorInspection
     */
    public function __construct()
    {
    }

    /**
     * Clean up the cURL handle.
     */
    public function __destruct()
    {
    }

    /**
     * Make a HTTP request.
     *
     * @param string $url
     * @param string $method
     * @param mixed  $params
     * @param array  $options
     *
     * @return HttpclientResponse
     */
    protected function request($url, $method = self::GET, $params = array(), $options = array())
    {
        $paths = parse_url($url);

        $host = $paths['host'];
        $is_ssl = isset($paths['scheme']) && $paths['scheme'] == 'https';
        $port = isset($paths['port']) ? $paths['port'] : ($is_ssl ? 443 : 80);

        // $ip = \Swoole\Coroutine::gethostbyname($host);
        // if ( !$ip ) {
        //     \throw_error('Coroutine::gethostbyname("'. $host .'") host is empty', 19220);
        // }

        $cli = new \Swoole\Coroutine\Http\Client($host, $port, $is_ssl);

        // 兼容httpclient的header头
        $headers = ['Host' => $host];
        if (isset($options['headers']) && count($options['headers'])) {
            foreach ($options['headers'] as $value) {
                $tmp = explode(':', $value);
                if (\count($tmp) > 1) {
                    $headers[ $tmp[0] ] = implode(':', array_slice($tmp, 1));
                }
            }
        }

        $uri = (isset($paths['path']) ? $paths['path'] : '/') . '?' . (isset($paths['query']) ? $paths['query'] : '');

        // Set request timeout
        $cli->set(['timeout' => isset($options['timeout']) ? $options['timeout'] * 1000 : 10e3 ]);

        // Support get, post
        if ( $method === self::GET ) {
            $uri .= '&' . http_build_query($params);
            $cli->setHeaders($headers);
            $cli->get( $uri );
        } else if ( $method === self::POST ) {
            if ( !empty($options['json']) ) {
                $params = json_encode($params, JSON_UNESCAPED_UNICODE);
                $headers['Content-type'] = 'application/json';
            }

            // Check for files
            if (isset($options['files']) && count($options['files'])) {
                foreach ($options['files'] as $index => $file) {
                    $cli->addFile($file, $index);
                }
            }

            $cli->setHeaders($headers);
            $cli->post( $uri, $params );
        } else {
            \throw_error('Unsupported method, only GET/POST', 18201);
        }

        // Close
        $cli->close();

        return new HttpclientResponse($cli->body, $cli->statusCode, $cli->getHeaders());
    }
}
