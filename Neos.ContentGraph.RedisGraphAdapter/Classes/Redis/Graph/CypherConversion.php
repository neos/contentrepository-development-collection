<?php


namespace Neos\ContentGraph\RedisGraphAdapter\Redis\Graph;


class CypherConversion
{

    static $search = ["\t", "\b", "\n", "\r", "\f", "'", '\\', '"'];
    static $replace = ['\t', '\b', '\n', '\r', '\f', '\\\'', '\\\\', '\"'];

    public static function propertiesToCypher(array $properties): string
    {
        $renderedProperties = [];

        foreach ($properties as $key => $element) {
            $renderedProperties[] = $key . ": '" . str_replace(self::$search, self::$replace, $element) . "'";
        }

        $renderedPropertyString = implode(', ', $renderedProperties);
        if ($renderedPropertyString !== '') {
            $renderedPropertyString = ' {' . $renderedPropertyString . '}';
        }

        return $renderedPropertyString;
    }

    public static function decodeStrings(string $in)
    {
        return str_replace(self::$replace, self::$search, $in);
    }
}
