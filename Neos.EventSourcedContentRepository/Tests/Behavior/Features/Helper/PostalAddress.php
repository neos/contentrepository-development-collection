<?php declare(strict_types=1);
namespace Neos\EventSourcedContentRepository\Tests\Behavior\Features\Helper;

/*
 * This file is part of the Neos.ContentRepository package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Flow\Annotations as Flow;

/**
 * The postal address value object for testing packages
 *
 * @Flow\Proxy(false)
 */
final class PostalAddress implements \JsonSerializable
{
    private string $streetAddress;

    private string $postalCode;

    private string $addressLocality;

    private string $addressCountry;

    private function __construct(
        string $streetAddress,
        string $postalCode,
        string $addressLocality,
        string $addressCountry
    ) {
        $this->streetAddress = $streetAddress;
        $this->postalCode = $postalCode;
        $this->addressLocality = $addressLocality;
        $this->addressCountry = $addressCountry;
    }

    public static function fromArray(array $array): self
    {
        return new self(
            $array['streetAddress'],
            $array['postalCode'],
            $array['addressLocality'],
            $array['addressCountry'],
        );
    }

    public static function dummy(): self
    {
        return new self(
            'Street of February 31st 28',
            '12345',
            'City',
            'Country'
        );
    }

    public function getStreetAddress(): string
    {
        return $this->streetAddress;
    }

    public function getPostalCode(): string
    {
        return $this->postalCode;
    }

    public function getAddressLocality(): string
    {
        return $this->addressLocality;
    }

    public function getAddressCountry(): string
    {
        return $this->addressCountry;
    }

    public function jsonSerialize(): array
    {
        return [
            'streetAddress' => $this->streetAddress,
            'postalCode' => $this->postalCode,
            'addressLocality' => $this->addressLocality,
            'addressCountry' => $this->addressCountry
        ];
    }
}
