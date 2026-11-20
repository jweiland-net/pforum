<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Functional\Validation\Validator;

use JWeiland\Pforum\Domain\Model\AnonymousUser;
use JWeiland\Pforum\Domain\Model\Post;
use JWeiland\Pforum\Domain\Model\Topic;
use JWeiland\Pforum\Validation\Validator\AnonymousUserValidator;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Extbase\Error\Error;
use TYPO3\CMS\Extbase\Error\Result;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case.
 */
class AnonymousUserValidatorTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'jweiland/pforum',
    ];

    #[Test]
    public function validTopicWithoutMandatoryFieldsHasNoErrors(): void
    {
        self::assertFalse($this->validate($this->buildTopic('', ''), false, false)->hasErrors());
    }

    #[Test]
    public function invalidEmailIsAlwaysAnError(): void
    {
        $result = $this->validate($this->buildTopic('john', 'john@'), false, false);

        self::assertSame([1457431804], $this->getErrorCodes($result, 'anonymousUser.email'));
    }

    #[Test]
    public function missingMandatoryEmailIsAnError(): void
    {
        $result = $this->validate($this->buildPost('john', ''), false, true);

        self::assertSame([1378288238], $this->getErrorCodes($result, 'anonymousUser.email'));
    }

    #[Test]
    public function missingMandatoryUsernameIsAnError(): void
    {
        $result = $this->validate($this->buildPost(' ', 'john@example.com'), true, true);

        self::assertSame([1378304890], $this->getErrorCodes($result, 'anonymousUser.username'));
        self::assertSame([], $this->getErrorCodes($result, 'anonymousUser.email'));
    }

    #[Test]
    public function topicWithoutPforumUserNeedsMandatoryFields(): void
    {
        $result = $this->validate(new Topic(), true, true);

        self::assertSame([1378304890], $this->getErrorCodes($result, 'anonymousUser.username'));
        self::assertSame([1378288238], $this->getErrorCodes($result, 'anonymousUser.email'));
    }

    private function validate(Topic|Post $record, bool $usernameIsMandatory, bool $emailIsMandatory): Result
    {
        $validator = new AnonymousUserValidator();
        $validator->setOptions(['usernameIsMandatory' => $usernameIsMandatory, 'emailIsMandatory' => $emailIsMandatory]);

        return $validator->validate($record);
    }

    /**
     * @return int[]
     */
    private function getErrorCodes(Result $result, string $propertyPath): array
    {
        return array_map(
            static fn(Error $error): int => $error->getCode(),
            $result->forProperty($propertyPath)->getErrors(),
        );
    }

    private function buildTopic(string $username, string $email): Topic
    {
        $topic = new Topic();
        $topic->setAnonymousUser($this->buildUser($username, $email));

        return $topic;
    }

    private function buildPost(string $username, string $email): Post
    {
        $post = new Post();
        $post->setAnonymousUser($this->buildUser($username, $email));

        return $post;
    }

    private function buildUser(string $username, string $email): AnonymousUser
    {
        $user = new AnonymousUser();
        $user->setUsername($username);
        $user->setEmail($email);

        return $user;
    }
}
