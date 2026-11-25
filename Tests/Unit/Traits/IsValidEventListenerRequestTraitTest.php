<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Unit\Traits;

use JWeiland\Pforum\Event\ControllerActionEventInterface;
use JWeiland\Pforum\Traits\IsValidEventListenerRequestTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case.
 */
class IsValidEventListenerRequestTraitTest extends UnitTestCase
{
    private object $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new class {
            use IsValidEventListenerRequestTrait;

            private const ALLOWED_CONTROLLER_ACTIONS = [
                'Topic' => ['create', 'update'],
                'Post' => ['create'],
            ];

            public function check(ControllerActionEventInterface $event): bool
            {
                return $this->isValidRequest($event);
            }
        };
    }

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function requestDataProvider(): array
    {
        return [
            'topic create' => ['Topic', 'create', true],
            'topic update' => ['Topic', 'update', true],
            'post create' => ['Post', 'create', true],
            'post update is not allowed' => ['Post', 'update', false],
            'topic delete' => ['Topic', 'delete', false],
            'unknown controller' => ['Forum', 'create', false],
            'controller name is case sensitive' => ['topic', 'create', false],
            'action name is case sensitive' => ['Topic', 'Create', false],
            'action with suffix' => ['Topic', 'createAction', false],
            'empty' => ['', '', false],
        ];
    }

    #[Test]
    #[DataProvider('requestDataProvider')]
    public function isValidRequestChecksControllerAndAction(string $controllerName, string $actionName, bool $expected): void
    {
        $event = $this->createMock(ControllerActionEventInterface::class);
        $event->method('getControllerName')->willReturn($controllerName);
        $event->method('getActionName')->willReturn($actionName);

        self::assertSame($expected, $this->subject->check($event));
    }
}
