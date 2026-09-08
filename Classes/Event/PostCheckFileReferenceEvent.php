<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Event;

use TYPO3\CMS\Core\Http\UploadedFile;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\Error\Error;

/*
 * Use this event, if you want to add further checks for uploaded images of pforum frontend form
 */
class PostCheckFileReferenceEvent
{
    protected ?Error $error = null;

    /**
     * @param array<array-key, mixed> $source
     */
    public function __construct(protected array $source, protected int $key, protected ?UploadedFile $uploadedFile = null, protected ?FileReference $alreadyPersistedImage = null)
    {
    }

    /**
     * @return array<array-key, mixed>
     */
    public function getSource(): array
    {
        return $this->source;
    }

    public function getKey(): int
    {
        return $this->key;
    }

    public function getUploadedFile(): UploadedFile
    {
        return $this->uploadedFile;
    }

    public function getAlreadyPersistedImage(): ?FileReference
    {
        return $this->alreadyPersistedImage;
    }

    public function getError(): ?Error
    {
        return $this->error;
    }

    public function setError(Error $error): void
    {
        $this->error = $error;
    }
}
