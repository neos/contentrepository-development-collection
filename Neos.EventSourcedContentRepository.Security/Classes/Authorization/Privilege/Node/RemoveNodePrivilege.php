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

use Neos\EventSourcedContentRepository\Domain\Projection\Content\NodeInterface;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Command\RemoveNodeAggregate;
use Neos\Flow\Security\Authorization\Privilege\Method\MethodPrivilegeSubject;
use Neos\Flow\Security\Authorization\Privilege\PrivilegeSubjectInterface;
use Neos\Flow\Security\Exception\InvalidPrivilegeTypeException;

/**
 * A privilege to remove nodes
 */
class RemoveNodePrivilege extends AbstractNodePrivilege
{
    /**
     * @param PrivilegeSubjectInterface|NodePrivilegeSubject|MethodPrivilegeSubject $subject
     * @return boolean
     * @throws InvalidPrivilegeTypeException
     */
    public function matchesSubject(PrivilegeSubjectInterface $subject)
    {
        if (!$subject instanceof NodePrivilegeSubject && !$subject instanceof MethodPrivilegeSubject) {
            throw new InvalidPrivilegeTypeException(sprintf(
                'Privileges of type "%s" only support subjects of type "%s" or "%s",'
                    . ' but we got a subject of type: "%s".',
                RemoveNodePrivilege::class,
                NodePrivilegeSubject::class,
                MethodPrivilegeSubject::class,
                get_class($subject)
            ), 1417017296);
        }

        if ($subject instanceof MethodPrivilegeSubject) {
            $this->initializeMethodPrivilege();
            if ($this->methodPrivilege->matchesSubject($subject) === false) {
                return false;
            }
            /** @var NodeInterface $node */
            $node = $subject->getJoinPoint()->getProxy();
            $nodePrivilegeSubject = new NodePrivilegeSubject($node);
            return parent::matchesSubject($nodePrivilegeSubject);
        }
        return parent::matchesSubject($subject);
    }

    /**
     * @return string
     */
    protected function buildMethodPrivilegeMatcher()
    {
        return  'method(' . RemoveNodeAggregate::class . '->__construct())';
    }
}
