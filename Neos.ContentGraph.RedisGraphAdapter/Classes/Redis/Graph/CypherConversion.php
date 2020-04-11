<?php


namespace Neos\ContentGraph\RedisGraphAdapter\Redis\Graph;


class CypherConversion
{

    public static function propertiesToCypher(array $properties): string
    {
        $renderedProperties = [];
        foreach ($properties as $key => $element) {
            $renderedProperties[] = $key . ": '" . $element . "'";
        }

        $renderedPropertyString = implode(', ', $renderedProperties);
        if ($renderedPropertyString !== '') {
            $renderedPropertyString = ' {' . $renderedPropertyString . '}';
        }

        return $renderedPropertyString;
    }
}
