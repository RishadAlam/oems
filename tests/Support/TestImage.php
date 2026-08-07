<?php

declare(strict_types=1);

namespace OEMS\Tests\Support;

use RuntimeException;

final class TestImage
{
    private const JPEG_BASE64 =
        '/9j/4AAQSkZJRgABAQEAYABgAAD//gA7Q1JFQVRPUjogZ2QtanBlZyB2MS4wICh1c2luZyBJSkcgSlBFRyB2ODApLCBxdWFsaXR5ID0gOTAK'
        . '/9sAQwADAgIDAgIDAwMDBAMDBAUIBQUEBAUKBwcGCAwKDAwLCgsLDQ4SEA0OEQ4LCxAWEBETFBUVFQwPFxgWFBgSFBUU/9sAQwEDBAQFBAUJ'
        . 'BQUJFA0LDRQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQU/8AAEQgACAAMAwERAAIRAQMRAf/E'
        . 'AB8AAAEFAQEBAQEBAAAAAAAAAAABAgMEBQYHCAkKC//EALUQAAIBAwMCBAMFBQQEAAABfQECAwAEEQUSITFBBhNRYQcicRQygZGhCCNCscEV'
        . 'UtHwJDNicoIJChYXGBkaJSYnKCkqNDU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6g4SFhoeIiYqSk5SVlpeYmZqio6Sl'
        . 'pqeoqaqys7S1tre4ubrCw8TFxsfIycrS09TV1tfY2drh4uPk5ebn6Onq8fLz9PX29/j5+v/EAB8BAAMBAQEBAQEBAQEAAAAAAAABAgMEBQYH'
        . 'CAkKC//EALURAAIBAgQEAwQHBQQEAAECdwABAgMRBAUhMQYSQVEHYXETIjKBCBRCkaGxwQkjM1LwFWJy0QoWJDThJfEXGBkaJicoKSo1Njc4'
        . 'OTpDREVGR0hJSlNUVVZXWFlaY2RlZmdoaWpzdHV2d3h5eoKDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT'
        . '1NXW19jZ2uLj5OXm5+jp6vLz9PX29/j5+v/aAAwDAQACEQMRAD8A8ar+tT8kCgD/2Q==';

    private const PNG_BASE64 =
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    private const WEBP_BASE64 =
        'UklGRiIAAABXRUJQVlA4IBYAAAAwAQCdASoBAAEAAUAmJaQAA3AA/vuUAAA=';

    public static function writeJpeg(string $path): string
    {
        $bytes = base64_decode(self::JPEG_BASE64, true);

        if ($bytes === false || file_put_contents($path, $bytes) === false) {
            throw new RuntimeException('Unable to write the embedded JPEG fixture.');
        }

        return $path;
    }

    public static function writePng(string $path): string
    {
        return self::writeFixture($path, self::PNG_BASE64);
    }

    public static function writeWebp(string $path): string
    {
        return self::writeFixture($path, self::WEBP_BASE64);
    }

    private static function writeFixture(string $path, string $base64): string
    {
        $bytes = base64_decode($base64, true);

        if ($bytes === false || file_put_contents($path, $bytes) === false) {
            throw new RuntimeException('Unable to write the embedded image fixture.');
        }

        return $path;
    }
}
