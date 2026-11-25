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
use JWeiland\Pforum\Tests\Functional\Traits\ImageUploadValidatorsTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Without the extension checkfaluploads the image uploads have no
 * validator of the extension. The class of the test double is autoloaded,
 * but the extension is not active.
 */
class CheckFalUploadsNotInstalledTest extends FunctionalTestCase
{
    use ImageUploadValidatorsTrait;

    protected array $testExtensionsToLoad = [
        'jweiland/pforum',
    ];

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function actionsProvider(): array
    {
        return [
            'create topic' => ['Topic', 'create', 'topic'],
            'update topic' => ['Topic', 'update', 'topic'],
            'create post' => ['Post', 'create', 'post'],
            'update post' => ['Post', 'update', 'post'],
        ];
    }

    #[Test]
    #[DataProvider('actionsProvider')]
    public function validatorIsNotAddedWithoutExtension(string $controllerName, string $actionName, string $argumentName): void
    {
        foreach ($this->getImageUploadValidators($controllerName, $actionName, $argumentName) as $validator) {
            self::assertNotInstanceOf(CheckFalUploadValidator::class, $validator);
        }
    }
}
