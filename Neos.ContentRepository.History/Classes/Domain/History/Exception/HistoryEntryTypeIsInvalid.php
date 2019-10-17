<?php
declare(strict_types=1);

namespace Neos\ContentRepository\History\Domain\History\Exception;

/*
 * This file is part of the Neos.ContentRepository.History package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

/**
 * The exception to be thrown if an invalid history entry type was tried to be initialized
 */
final class HistoryEntryTypeIsInvalid extends \DomainException
{
    public static function becauseItIsNotOneOfTheDefinedConstants(string $attemptedValue): HistoryEntryTypeIsInvalid
    {
        return new static('Given value "' . $attemptedValue . '" is no valid history entry type, must be one of the defined constants."', 1571349403);
    }
}
