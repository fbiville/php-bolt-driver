<?php


class BoltDriver
{
    /*
     *  Supported Bolt protocol version in descending preference order
     */
    private static $SUPPORTED_VERSIONS = [4];

    private $host;

    private $port;

    public function __construct(string $host = 'localhost', int $port = 7687)
    {
        $this->host = $host;
        $this->port = $port;
    }


    public function connect()
    {
        $socket = null;
        try {
            $socket = self::doConnect();
            self::negotiateVersion($socket);
            $helloMessage = pack(
                // symbols
                'n' . // total size
                'n' . // message type
                'c' . // first field
                'c' . // first entry
                'a' .
                'c' .
                'a' .
                'c' . // second entry
                'a' .
                'c' .
                'a' .
                'c' . // third entry
                'a' .
                'c' .
                'a' .
                'c' . // fourth entry
                'a' .
                'c' .
                'a' .
                'n', // message end
                // values
                75,
                0xB101, // structure of 1 field + INIT/HELLO tag byte
                0xA4, // first field: dictionary of 4 entries
                0x8A, // first key marker: string of 10 bytes
                "user_agent", // first key
                0x8E, // first value marker: string of 14 bytes
                "Fbiville/0.0.0", // first value
                0x86, // second key marker: string of 6 bytes
                "scheme", // second key
                0x85, // second value marker: string of 5 bytes
                "basic", // second value
                0x89, // third key marker: string of 9 bytes
                "principal", // third key
                0x85, // third value marker: string of 5 bytes
                "neo4j", // third value
                0x8B, // fourth key marker: string of 11 bytes
                "credentials", // fourth key
                0x84, // fourth value marker: string of 4 bytes
                "toto", // fourth value
                0);
            self::write($socket, $helloMessage);
            $response = socket_read($socket, 255);
            var_dump($response);
        } finally {
            socket_close($socket);
        }
    }

    /**
     * @return false|resource
     */
    private static function doConnect()
    {
        $socket = socket_create(AF_INET, SOCK_STREAM, getprotobyname('tcp'));
        if (!socket_connect($socket, 'localhost', 7687)) {
            throw new RuntimeException('💥 Socket connection failed');
        }
        return $socket;
    }

    private static function boltPreambleMessage()
    {
        return pack('nn', 0x6060, 0xB017);
    }

    private static function negotiateVersion($socket): void
    {
        self::write($socket, self::boltPreambleMessage());
        $versionNegotiation = self::supportedVersionsMessage(self::$SUPPORTED_VERSIONS);
        self::write($socket, $versionNegotiation);
        $response = socket_read($socket, 32);
        $serverVersion = unpack('N', $response);
        if ($serverVersion === 0) {
            throw new RuntimeException('😭 Server does not support any of the supported driver version');
        }
    }


    private static function write($socket, string $payload): void
    {
        if (socket_write($socket, $payload, strlen($payload)) === false) {
            throw new RuntimeException('💥 Write failed, code: ' . socket_last_error($socket));
        }
    }

    private static function supportedVersionsMessage(array $versions): string
    {
        $normalizedVersions = self::normalizeVersions($versions);
        return pack('NNNN', ...$normalizedVersions);
    }

    private static function normalizeVersions(array $versions): array
    {
        return array_pad(
            array_slice($versions, 0, 4),
            4, 0
        );
    }
}