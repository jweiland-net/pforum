<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\EventListener;

use JWeiland\Pforum\Event\PostProcessFluidVariablesEvent;
use JWeiland\Pforum\Traits\IsValidEventListenerRequestTrait;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Pagination\SimplePagination;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Extbase\Pagination\QueryResultPaginator;

#[AsEventListener(
    identifier: 'pforum/add-paginator',
)]
final readonly class AddPaginatorEventListener
{
    use IsValidEventListenerRequestTrait;

    private const ITEMS_PER_PAGE = 15;

    private const ALLOWED_CONTROLLER_ACTIONS = [
        'Forum' => [
            'show',
        ],
        'Topic' => [
            'show',
        ],
    ];

    public function __invoke(PostProcessFluidVariablesEvent $event): void
    {
        $typeOfPaginatedItems = 'topics';
        if ($event->getControllerName() === 'Topic') {
            $typeOfPaginatedItems = 'posts';
        }

        if (!$this->isValidRequest($event)) {
            return;
        }

        $paginator = new QueryResultPaginator(
            $event->getFluidVariables()[$typeOfPaginatedItems],
            $this->getCurrentPage($event),
            $this->getItemsPerPage($event),
        );

        $event->addFluidVariable('actionName', $event->getActionName());
        $event->addFluidVariable('paginator', $paginator);
        $event->addFluidVariable($typeOfPaginatedItems, $paginator->getPaginatedItems());
        $event->addFluidVariable('pagination', new SimplePagination($paginator));
    }

    private function getCurrentPage(PostProcessFluidVariablesEvent $event): int
    {
        if ($event->getRequest()->hasArgument('currentPage')) {
            return MathUtility::forceIntegerInRange(
                (int)$event->getRequest()->getArgument('currentPage'),
                1,
            );
        }

        return 1;
    }

    private function getItemsPerPage(PostProcessFluidVariablesEvent $event): int
    {
        return (int)($event->getSettings()['pageBrowser']['itemsPerPage'] ?? self::ITEMS_PER_PAGE);
    }
}
