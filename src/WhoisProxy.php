<?php

namespace btrsco\WhoisProxy;

class WhoisProxy
{
    /**
     * Proxy Host / IP
     * @var $_proxyHost
     */
    protected $_proxyHost;

    /**
     * Proxy Port
     * @var $_proxyPort
     */
    protected $_proxyPort;

    /**
     * Socket Connection Timeout
     * @var $_timeout
     */
    protected $_timeout;

    /**
     * Default Authoritative Server
     * @var $_defaultServer
     */
    protected $_defaultServer;

    /**
     * Max Loop Count
     * @var $_maxLoop
     */
    protected $_maxLoop;

    /**
     * WhoisProxy constructor.
     * @param $options
     */
    public function __construct( $options )
    {
        $this->setProxyHost( $options['host'] ?? '127.0.0.1' );
        $this->setProxyPort( $options['port'] ?? 8080 );
        $this->setTimeout( $options['timeout'] ?? 10 );
        $this->setDefaultServer( 'whois.iana.org' );
        $this->setMaxLoop( 512 );
    }

    /**
     * Get Proxy Host / IP
     * @return mixed
     */
    protected function getProxyHost()
    {
        return $this->_proxyHost;
    }

    /**
     * Set Proxy Host / IP
     * @param mixed $proxyHost
     */
    protected function setProxyHost( $proxyHost ): void
    {
        $this->_proxyHost = $proxyHost;
    }

    /**
     * Get Proxy Port
     * @return mixed
     */
    protected function getProxyPort()
    {
        return $this->_proxyPort;
    }

    /**
     * Set Proxy Port
     * @param mixed $proxyPort
     */
    protected function setProxyPort( $proxyPort ): void
    {
        $this->_proxyPort = $proxyPort;
    }

    /**
     * Get Socket Connection Timeout
     * @return mixed
     */
    protected function getTimeout()
    {
        return $this->_timeout;
    }

    /**
     * Set Socket Connection Timeout
     * @param mixed $timeout
     */
    protected function setTimeout( $timeout ): void
    {
        $this->_timeout = $timeout;
    }

    /**
     * Get Authoritative Server
     * @return mixed
     */
    protected function getDefaultServer()
    {
        return $this->_defaultServer;
    }

    /**
     * Set Authoritative Server
     * @param mixed $defaultServer
     */
    protected function setDefaultServer( $defaultServer ): void
    {
        $this->_defaultServer = $defaultServer;
    }

    /**
     * Get Max Loop Count
     * @return mixed
     */
    public function getMaxLoop()
    {
        return $this->_maxLoop;
    }

    /**
     * Set Max Loop Count
     * @param mixed $maxLoop
     */
    public function setMaxLoop( $maxLoop ): void
    {
        $this->_maxLoop = $maxLoop;
    }

    /**
     * Initialize HTTP Proxy Connection
     * @return mixed
     */
    protected function initializeConnection()
    {
        return fsockopen(
            $this->getProxyHost(),
            $this->getProxyPort(),
            $errno,
            $error,
            $this->getTimeout()
        );
    }

    /**
     * Terminate HTTP Proxy Connection
     * @param $connection
     */
    protected function terminateConnection( $connection )
    {
        fclose( $connection );
    }

    /**
     * Parse Line of Raw Whois Result
     * @param $key
     * @param $haystack
     * @return string|null
     */
    public function parseValue( $key, $haystack )
    {
        preg_match_all( "/$key(.*)\n/mU", strtolower( $haystack ), $matches, PREG_SET_ORDER, 0 );
        return empty( $matches ) ? null : trim( $matches[0][1] );
    }

    /**
     * Query Whois Server for Information
     * @param $domain
     * @param null $server
     * @return string
     */
    public function queryWhois( $domain, $server = null )
    {
        $connection = $this->initializeConnection();
        $whoisData  = "";
        $loopCount  = 0;

        $domain = strtoupper( trim( $domain ) ) . "\r\n";
        $server = strtoupper( $server ? $server : $this->getDefaultServer() ) . ":43\r\n";

        fputs( $connection, "CONNECT $server" );
        fputs( $connection, "$domain" );

        while ( !feof( $connection ) ) {
            $loopCount++;
            $whoisData .= trim( fgets( $connection, 1024 ) ) . PHP_EOL;
            if ( $loopCount > $this->getMaxLoop() ) break;
        }

        $this->terminateConnection( $connection );
        return $whoisData;
    }

    /**
     * Get Appropriate Whois Server for Future Queries
     * @param $domain
     * @param null $server
     * @return string|null
     */
    public function getWhoisServer( $domain, $server = null )
    {
        $whoisData = $this->queryWhois( $domain, $server );

        foreach ( config('whoisproxy.patterns.whois') as $pattern ) {
            $match = $this->parseValue( $pattern, $whoisData );
            if ( !is_null( $match ) ) return $match;
        }

        return null;
    }
}
