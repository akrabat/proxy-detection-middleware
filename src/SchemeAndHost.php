<?php
namespace RKA\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

class SchemeAndHost
{
    /**
     * List of trusted proxy IP addresses
     *
     * If not empty, then one of these IP addresses must be in $_SERVER['REMOTE_ADDR']
     * in order for the proxy headers to be looked at.
     *
     * @var array
     */
    protected $trustedProxies;

    /**
     * Constructor
     *
     * @param array $trustedProxies   List of IP addresses of trusted proxies
     */
    public function __construct($trustedProxies = [])
    {
        $this->trustedProxies = $trustedProxies;
    }

    /**
     * Override the request URI's scheme, host and port as determined from the proxy headers
     *
     * @param ServerRequestInterface $request PSR7 request
     * @param ResponseInterface $response     PSR7 response
     * @param callable $next                  Next middleware
     *
     * @return ResponseInterface
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, $next)
    {
        if (!$next) {
            return $response;
        }
        
        if (!empty($this->trustedProxies)) {
            // get IP address from REMOTE_ADDR
            $ipAddress = null;
            $serverParams = $request->getServerParams();
            if (isset($serverParams['REMOTE_ADDR']) && $this->isValidIpAddress($serverParams['REMOTE_ADDR'])) {
                $ipAddress = $serverParams['REMOTE_ADDR'];
            }

            if (!in_array($ipAddress, $this->trustedProxies)) {
                return $response = $next($request, $response);
            }
        }
        
        $uri = $request->getUri();

        // scheme
        if ($request->hasHeader('X-Forwarded-Proto')) {
            $scheme = $request->getHeaderLine('X-Forwarded-Proto');
            if (in_array($scheme, ['http', 'https'])) {
                $uri = $uri->withScheme($scheme);
            }
        }

        // host (& maybe port too)
        if ($request->hasHeader('X-Forwarded-Host')) {
            $host = trim(current(explode(',', $request->getHeaderLine('X-Forwarded-Host'))));
            
            $port = null;
            if (preg_match('/^(\[[a-fA-F0-9:.]+\])(:\d+)?\z/', $host, $matches)) {
                $host = $matches[1];
                if ($matches[2]) {
                    $port = (int) substr($matches[2], 1);
                }
            } else {
                $pos = strpos($host, ':');
                if ($pos !== false) {
                    $port = (int) substr($host, $pos + 1);
                    $host = strstr($host, ':', true);
                }
            }
            $uri = $uri->withHost($host);
            if ($port) {
                $uri = $uri->withPort($port);
            }
        }

        $request = $request->withUri($uri);
        
        return $response = $next($request, $response);
    }

    /**
     * Check that a given string is a valid IP address
     *
     * @param  string  $ip
     * @return boolean
     */
    protected function isValidIpAddress($ip)
    {
        $flags = FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6;
        if (filter_var($ip, FILTER_VALIDATE_IP, $flags) === false) {
            return false;
        }
        return true;
    }

    /**
     * Getter for headers
     *
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * Setter for headers
     *
     * @param array $headers List of proxy headers to test against
     * @return self
     */
    public function setHeaders(array $headers)
    {
        $this->headers = $headers;
        return $this;
    }
}
