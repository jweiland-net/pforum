<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Functional\Fixtures\Classes;

use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ResourceStorage;

/**
 * Functional tests can not create files, for which is_uploaded_file()
 * returns true. This storage skips this check only. All other checks of
 * the upload (size, file extension, permissions) are kept.
 */
class ResourceStorageWithoutUploadCheck extends ResourceStorage
{
    /**
     * @param string $localFilePath
     * @param Folder $targetFolder
     * @param string $targetFileName
     * @param int $uploadedFileSize
     */
    protected function assureFileUploadPermissions($localFilePath, $targetFolder, $targetFileName, $uploadedFileSize)
    {
        $this->assureFileAddPermissions($targetFolder, $targetFileName);
    }
}
