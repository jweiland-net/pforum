<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Event;

use JWeiland\Pforum\Domain\Model\Post;
use JWeiland\Pforum\Domain\Model\Topic;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Dispatched after a topic or post was published in frontend: a new visible
 * record was saved, or a hidden record was activated by its author or an
 * admin. A record saved as hidden is not published.
 */
final readonly class RecordPublishedEvent
{
    /**
     * @param array<string, mixed> $settings Plugin settings
     */
    public function __construct(
        private Topic|Post $record,
        private array $settings,
        private ServerRequestInterface $request,
    ) {}

    public function getRecord(): Topic|Post
    {
        return $this->record;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSettings(): array
    {
        return $this->settings;
    }

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }
}
