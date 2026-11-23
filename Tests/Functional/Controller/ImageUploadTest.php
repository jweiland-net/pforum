<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Functional\Controller;

use JWeiland\Pforum\Tests\Functional\Fixtures\Classes\ResourceStorageWithoutUploadCheck;
use JWeiland\Pforum\Tests\Functional\Traits\FrontendRequestTrait;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\UploadedFile;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Images of topics and posts are uploaded without a rights checkbox. The
 * rights check of an overridden checkbox is done by EXT:checkfaluploads.
 * The storage skips the check of is_uploaded_file(), see
 * ResourceStorageWithoutUploadCheck.
 */
class ImageUploadTest extends FunctionalTestCase
{
    use FrontendRequestTrait;

    protected array $testExtensionsToLoad = [
        'jweiland/pforum',
    ];

    protected array $configurationToUseInTestInstance = [
        'MAIL' => [
            'transport' => 'null',
        ],
        'SYS' => [
            'Objects' => [
                ResourceStorage::class => [
                    'className' => ResourceStorageWithoutUploadCheck::class,
                ],
            ],
        ],
        'EXTENSIONS' => [
            'pforum' => [
                'emailFromAddress' => 'forum@example.com',
                'emailFromName' => 'Forum',
            ],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Forum.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/FileStorage.csv');
        $this->setUpForumSite();
    }

    #[Test]
    public function createStoresImageOfTopic(): void
    {
        $this->submitForm('Topic', 'create', [], [
            'forum' => '1',
            'topic' => [
                'title' => 'Topic with image',
                'description' => 'My question',
                'anonymousUser' => ['name' => 'John', 'username' => 'john', 'email' => 'john@example.com'],
            ],
        ], 0, '', [], ['topic' => ['images' => [$this->buildUploadedFile('topic.png')]]]);

        $this->assertImageIsStored('tx_pforum_domain_model_topic', 'Topic with image', 'topic.png');
    }

    #[Test]
    public function createStoresImageOfPost(): void
    {
        $this->submitForm('Post', 'create', [], [
            'topic' => '1',
            'post' => [
                'title' => 'Post with image',
                'description' => 'My answer',
                'anonymousUser' => ['name' => 'John', 'username' => 'john', 'email' => 'john@example.com'],
            ],
        ], 0, '', [], ['post' => ['images' => [$this->buildUploadedFile('post.png')]]]);

        $this->assertImageIsStored('tx_pforum_domain_model_post', 'Post with image', 'post.png');
    }

    private function assertImageIsStored(string $table, string $title, string $fileName): void
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();
        $record = $queryBuilder->select('uid', 'images')->from($table)
            ->where($queryBuilder->expr()->eq('title', $queryBuilder->createNamedParameter($title)))
            ->executeQuery()->fetchAssociative();
        self::assertIsArray($record, 'The record was not stored');
        self::assertSame(1, (int)$record['images']);

        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('sys_file_reference');
        $fileUid = $queryBuilder->select('uid_local')->from('sys_file_reference')
            ->where(
                $queryBuilder->expr()->eq('tablenames', $queryBuilder->createNamedParameter($table)),
                $queryBuilder->expr()->eq('fieldname', $queryBuilder->createNamedParameter('images')),
                $queryBuilder->expr()->eq('uid_foreign', (int)$record['uid']),
            )
            ->executeQuery()->fetchOne();

        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('sys_file');
        $identifier = $queryBuilder->select('identifier')->from('sys_file')
            ->where($queryBuilder->expr()->eq('uid', (int)$fileUid))
            ->executeQuery()->fetchOne();

        // Extbase adds a random suffix to the name of uploaded files
        [$name, $extension] = explode('.', $fileName);
        self::assertMatchesRegularExpression('#^/user_upload/tx_pforum/' . $name . '-[0-9a-f]+\\.' . $extension . '$#', (string)$identifier);
        self::assertFileExists($this->instancePath . '/fileadmin' . $identifier);
    }

    private function buildUploadedFile(string $fileName): UploadedFile
    {
        // A PNG image of 1x1 pixel
        $file = tempnam(sys_get_temp_dir(), 'pforum');
        file_put_contents($file, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));

        return new UploadedFile($file, (int)filesize($file), UPLOAD_ERR_OK, $fileName, 'image/png');
    }
}
