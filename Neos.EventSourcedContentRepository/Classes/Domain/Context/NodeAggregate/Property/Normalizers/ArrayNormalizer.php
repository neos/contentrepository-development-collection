<?php
declare(strict_types=1);

namespace Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Property\Normalizers;

use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class ArrayNormalizer implements DenormalizerInterface
{
    public function denormalize($data, $type, string $format = null, array $context = [])
    {
        return $data;
    }

    public function supportsDenormalization($data, $type, string $format = null)
    {
        return $type === 'array';
    }
}
