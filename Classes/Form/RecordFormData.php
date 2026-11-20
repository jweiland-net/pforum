<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Form;

/**
 * The submitted values of the form of a topic or post, prepared to be stored
 */
final readonly class RecordFormData
{
    /**
     * @param array<string, string> $columns Values of the columns of the topic or post table
     * @param array<string, string> $userColumns Values of the columns of the pforum user (anonymous user) table
     * @param array<string, int[]> $files UIDs of sys_file for each file column of the topic or post table
     */
    public function __construct(
        public array $columns = [],
        public array $userColumns = [],
        public array $files = [],
    ) {}
}
