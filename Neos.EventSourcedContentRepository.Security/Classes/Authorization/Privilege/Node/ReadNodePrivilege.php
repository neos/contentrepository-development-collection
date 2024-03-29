<?php
namespace Neos\EventSourcedContentRepository\Security\Authorization\Privilege\Node;

/*
 * This file is part of the Neos.ContentRepository package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Eel\CompilingEvaluator;
use Neos\Eel\Context;
use Neos\Flow\Security\Authorization\Privilege\AbstractPrivilege;
use Neos\Flow\Security\Authorization\Privilege\PrivilegeSubjectInterface;
use Neos\Flow\Security\Exception\InvalidPrivilegeTypeException;

/**
 * A node privilege to restricting reading of nodes.
 * Nodes not granted for reading will be filtered via SQL.
 *
 * Currently only doctrine persistence is supported as we use
 * the doctrine filter api, to rewrite SQL queries.
 */
class ReadNodePrivilege extends AbstractPrivilege
{

    /**
     * @param PrivilegeSubjectInterface $subject
     * @return boolean
     * @throws InvalidPrivilegeTypeException
     */
    public function matchesSubject(PrivilegeSubjectInterface $subject)
    {
        if (!$subject instanceof NodePrivilegeSubject) {
            throw new InvalidPrivilegeTypeException(sprintf(
                'Privileges of type "%s" only support subjects of type "%s", but we got a subject of type: "%s".',
                static::class,
                NodePrivilegeSubject::class,
                get_class($subject)
            ), 1465979693);
        }
        $nodeContext = new NodePrivilegeContext($subject->getNode());
        $eelContext = new Context($nodeContext);
        /** @var CompilingEvaluator $eelCompilingEvaluator */
        $eelCompilingEvaluator = $this->objectManager->get(CompilingEvaluator::class);
        return $eelCompilingEvaluator->evaluate($this->getParsedMatcher(), $eelContext);
    }
}
