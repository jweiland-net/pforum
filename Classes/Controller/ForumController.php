<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Controller;

use JWeiland\Pforum\Domain\Model\Forum;
use Psr\Http\Message\ResponseInterface;

/**
 * Main controller to list and show forum entries
 */
class ForumController extends AbstractController
{
    public function listAction(): ResponseInterface
    {
        $this->postProcessAndAssignFluidVariables([
            'forums' => $this->forumRepository->findAll(),
        ]);

        return $this->htmlResponse();
    }

    public function showAction(Forum $forum): ResponseInterface
    {
        $topics = $this->topicRepository->findByForum($forum);
        if ($this->frontendGroupHelper->uidExistsInGroupData((int)($this->settings['uidOfAdminGroup'] ?? 0))) {
            $topics->getQuery()
                ->getQuerySettings()
                ->setIgnoreEnableFields(true)
                ->setEnableFieldsToBeIgnored(['disabled']);
        }

        $this->postProcessAndAssignFluidVariables([
            'forum' => $forum,
            'topics' => $topics,
        ]);

        return $this->htmlResponse();
    }
}
