<?php
namespace RKA\Middleware\Test;

use RKA\Middleware\SchemeAndHost;
use Zend\Diactoros\ServerRequest;
use Zend\Diactoros\ServerRequestFactory;
use Zend\Diactoros\Uri;
use Zend\Diactoros\Response;
use Zend\Diactoros\Stream;
use RuntimeException;

class SchemeAndHostTest extends \PHPUnit_Framework_TestCase
{
    public function testShemeAndHost()
    {
        $request = ServerRequestFactory::fromGlobals([
            'REMOTE_ADDR' => '192.168.0.1',
            'HTTP_HOST' => 'foo.com',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_HOST' => 'example.com',
        ]);

        $response = new Response();

        $middleware = new SchemeAndHost([]);
        $response  = $middleware($request, $response, function ($request, $response) use (&$scheme, &$host) {
            // simply store the scheme and host values
            $scheme = $request->getUri()->getScheme();
            $host = $request->getUri()->getHost();
            return $response;
        });

        $this->assertSame('https', $scheme);
        $this->assertSame('example.com', $host);
    }

    public function testTrustedProxies()
    {
        $request = ServerRequestFactory::fromGlobals([
            'REMOTE_ADDR' => '192.168.0.1',
            'HTTP_HOST' => 'foo.com',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_HOST' => 'example.com',
        ]);

        $response = new Response();

        $middleware = new SchemeAndHost(['192.168.0.1']);
        $response  = $middleware($request, $response, function ($request, $response) use (&$scheme, &$host) {
            // simply store the scheme and host values
            $scheme = $request->getUri()->getScheme();
            $host = $request->getUri()->getHost();
            return $response;
        });

        $this->assertSame('https', $scheme);
        $this->assertSame('example.com', $host);
    }

    public function testNonTrustedProxies()
    {
        $request = ServerRequestFactory::fromGlobals([
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_HOST' => 'foo.com',
            'HTTP_X_FORWARDED_HOST' => 'example.com',
        ]);

        $response = new Response();

        $middleware = new SchemeAndHost(['192.168.0.1']);
        $response  = $middleware($request, $response, function ($request, $response) use (&$scheme, &$host) {
            // simply store the scheme and host values
            $scheme = $request->getUri()->getScheme();
            $host = $request->getUri()->getHost();
            return $response;
        });

        $this->assertSame('foo.com', $host);
    }
}
