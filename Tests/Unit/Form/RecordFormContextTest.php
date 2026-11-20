<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Unit\Form;

use JWeiland\Pforum\Configuration\ForumSettings;
use JWeiland\Pforum\Form\RecordFormContext;
use JWeiland\Pforum\Form\RecordType;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case.
 */
class RecordFormContextTest extends UnitTestCase
{
    #[Test]
    public function pluginArgumentsOfNewTopicContainForum(): void
    {
        $formContext = new RecordFormContext(RecordType::Topic, 0, 3, '', new ForumSettings());

        self::assertTrue($formContext->isNew());
        self::assertSame(['forum' => 3], $formContext->getPluginArguments());
    }

    #[Test]
    public function pluginArgumentsOfNewPostContainTopic(): void
    {
        $formContext = new RecordFormContext(RecordType::Post, 0, 5, '', new ForumSettings());

        self::assertSame(['topic' => 5], $formContext->getPluginArguments());
    }

    #[Test]
    public function pluginArgumentsOfEditedPostContainPostAndToken(): void
    {
        $formContext = new RecordFormContext(RecordType::Post, 7, 5, 'abc', new ForumSettings());

        self::assertFalse($formContext->isNew());
        self::assertSame(['post' => 7, 'token' => 'abc'], $formContext->getPluginArguments());
    }

    #[Test]
    public function pluginArgumentsOfTopicOfFrontendUserContainNoToken(): void
    {
        $formContext = new RecordFormContext(RecordType::Topic, 7, 3, '', new ForumSettings(auth: 2));

        self::assertSame(['topic' => 7], $formContext->getPluginArguments());
    }
}
