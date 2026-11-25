<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Service;

use JWeiland\Pforum\Domain\Model\FrontendUser;
use JWeiland\Pforum\Domain\Model\Post;
use JWeiland\Pforum\Domain\Model\Topic;
use JWeiland\Pforum\Domain\RecordType;
use JWeiland\Pforum\Domain\Repository\FrontendUserRepository;
use JWeiland\Pforum\Domain\Repository\PostRepository;
use JWeiland\Pforum\Domain\Repository\TopicRepository;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Extbase\Persistence\Generic\Session;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

/**
 * Finds, stores, activates and removes the topics and posts of the
 * frontend. Topics and posts are hidden until they are activated, so they
 * are found, even if they are hidden.
 */
readonly class RecordPersistenceService
{
    public function __construct(
        protected TopicRepository $topicRepository,
        protected PostRepository $postRepository,
        protected FrontendUserRepository $frontendUserRepository,
        protected PersistenceManagerInterface $persistenceManager,
        protected Session $session,
        protected PathSegmentService $pathSegmentService,
        protected LoggerInterface $logger,
    ) {}

    public function find(RecordType $recordType, int $uid): Topic|Post|null
    {
        if ($uid <= 0) {
            return null;
        }

        return match ($recordType) {
            RecordType::Topic => $this->topicRepository->findHiddenObject($uid),
            RecordType::Post => $this->postRepository->findHiddenObject($uid),
        };
    }

    /**
     * Hidden records can not be found by the property mapper of Extbase. A
     * registered record is used by the property mapper instead.
     */
    public function registerForPropertyMapping(Topic|Post $record): void
    {
        $uid = $record->getUid();
        if ($uid === null) {
            return;
        }

        $this->session->registerObject($record, (string)$uid);
    }

    /**
     * The logged-in frontend user is the author of the record
     */
    public function assignFrontendUser(Topic|Post $record, int $frontendUserUid): void
    {
        $frontendUser = $frontendUserUid > 0 ? $this->frontendUserRepository->findByIdentifier($frontendUserUid) : null;
        if ($frontendUser instanceof FrontendUser) {
            $record->setFrontendUser($frontendUser);
        }
    }

    /**
     * Stores the record and generates its path segment. New records get a
     * new path segment, as their title may change until they are published.
     * Published records keep their path segment, so their URL is kept.
     */
    public function persist(Topic|Post $record, bool $isNew): void
    {
        if ($record->getUid() !== null) {
            $this->persistenceManager->update($record);
        }
        $this->persistenceManager->persistAll();

        $recordType = RecordType::fromRecord($record);
        $this->pathSegmentService->generate($recordType, (int)$record->getUid(), $isNew);
        $this->logger->info('{type} {uid} saved', [
            'type' => $recordType->getControllerName(),
            'uid' => $record->getUid(),
            'isNew' => $isNew,
            'hidden' => $record->getHidden(),
        ]);
    }

    public function activate(Topic|Post $record): void
    {
        $record->setHidden(false);
        $this->persistenceManager->update($record);
        $this->logger->info('{type} {uid} activated', [
            'type' => RecordType::fromRecord($record)->getControllerName(),
            'uid' => $record->getUid(),
        ]);
    }

    public function remove(Topic|Post $record): void
    {
        $record instanceof Topic ? $this->topicRepository->remove($record) : $this->postRepository->remove($record);
        $this->logger->info('{type} {uid} deleted', [
            'type' => RecordType::fromRecord($record)->getControllerName(),
            'uid' => $record->getUid(),
        ]);
    }
}
