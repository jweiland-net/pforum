<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Functional\Controller;

use JWeiland\Checkfaluploads\Validation\Validator\CheckFalUploadValidator;
use JWeiland\Pforum\Tests\Functional\Traits\FrontendRequestTrait;
use JWeiland\Pforum\Tests\Functional\Traits\ImageUploadValidatorsTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * EXT:checkfaluploads has no release for TYPO3 14. So the functional tests
 * load a test double of the extension (Fixtures/Extensions/checkfaluploads)
 * and test the contract of pforum: the validator of the extension is added
 * to the image uploads of topics and posts. The integration of the real
 * extension is verified, as soon as it supports TYPO3 14.
 */
class CheckFalUploadsTest extends FunctionalTestCase
{
    use FrontendRequestTrait;
    use ImageUploadValidatorsTrait;

    protected array $testExtensionsToLoad = [
        '../../Tests/Functional/Fixtures/Extensions/checkfaluploads',
        'jweiland/pforum',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Forum.csv');
        $this->setUpForumSite();
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function actionsWithValidatorProvider(): array
    {
        return [
            'create topic' => ['Topic', 'create', 'topic'],
            'update topic' => ['Topic', 'update', 'topic'],
            'create post' => ['Post', 'create', 'post'],
            'update post' => ['Post', 'update', 'post'],
        ];
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function actionsWithoutValidatorProvider(): array
    {
        return [
            'new topic' => ['Topic', 'new', 'topic'],
            'edit topic' => ['Topic', 'edit', 'topic'],
            'delete topic' => ['Topic', 'delete', 'topic'],
            'activate topic' => ['Topic', 'activate', 'topic'],
            'show topic' => ['Topic', 'show', 'topic'],
            'new post' => ['Post', 'new', 'post'],
            'edit post' => ['Post', 'edit', 'post'],
            'delete post' => ['Post', 'delete', 'post'],
            'activate post' => ['Post', 'activate', 'post'],
            'create of forum controller' => ['Forum', 'create', 'topic'],
        ];
    }

    #[Test]
    public function formRendersNoRightsCheckbox(): void
    {
        $content = (string)$this->request('Topic', 'new', ['forum' => '1'])->getBody();

        self::assertStringContainsString('name="tx_pforum_forum[topic][images][]"', $content);
        self::assertStringNotContainsString('[images][rights]', $content);
    }

    #[Test]
    #[DataProvider('actionsWithValidatorProvider')]
    public function validatorIsAddedToImagesWithPropertyPathOfArgument(
        string $controllerName,
        string $actionName,
        string $argumentName,
    ): void {
        $validators = $this->getCheckFalUploadValidators(
            $this->getImageUploadValidators($controllerName, $actionName, $argumentName),
        );

        self::assertCount(1, $validators);
        self::assertSame(['propertyPath' => $argumentName . '.images'], $validators[0]->getOptions());
    }

    #[Test]
    #[DataProvider('actionsWithoutValidatorProvider')]
    public function validatorIsNotAddedForOtherActions(string $controllerName, string $actionName, string $argumentName): void
    {
        self::assertSame(
            [],
            $this->getCheckFalUploadValidators($this->getImageUploadValidators($controllerName, $actionName, $argumentName)),
        );
    }

    /**
     * @param object[] $validators
     * @return CheckFalUploadValidator[]
     */
    private function getCheckFalUploadValidators(array $validators): array
    {
        return array_values(array_filter(
            $validators,
            static fn(object $validator): bool => $validator instanceof CheckFalUploadValidator,
        ));
    }
}
