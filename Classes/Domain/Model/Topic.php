<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Domain\Model;

use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Annotation as Extbase;
use TYPO3\CMS\Extbase\Annotation\ORM\Cascade;
use TYPO3\CMS\Extbase\Annotation\ORM\Lazy;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

/**
 * Topic model as part of a forum entry
 */
class Topic extends AbstractEntity
{
    protected bool $hidden = false;

    protected \DateTime $crdate;

    protected ?Forum $forum = null;

    #[Extbase\Validate(['validator' => 'NotEmpty'])]
    protected string $title = '';

    #[Extbase\Validate(['validator' => 'NotEmpty'])]
    protected string $description = '';

    /**
     * @var ObjectStorage<Post>
     */
    #[Cascade(['value' => 'remove'])]
    #[Lazy]
    protected ObjectStorage $posts;

    #[Cascade(['value' => 'remove'])]
    protected ?AnonymousUser $anonymousUser = null;

    protected ?FrontendUser $frontendUser = null;

    /**
     * @var ObjectStorage<FileReference>
     */
    #[Lazy]
    #[Extbase\FileUpload([
        'validation' => [
            'required' => false,
            'maxFiles' => 2,
            'fileSize' => [
                'minimum' => '0K',
                'maximum' => '4M',
            ],
            'mimeType' => [
                'allowedMimeTypes' => [
                    'image/jpeg',
                    'image/png',
                ],
            ],
            'fileExtension' => [
                'allowedFileExtensions' => [
                    'jpg',
                    'jpeg',
                    'png',
                ],
            ],
        ],
        'uploadFolder' => '1:/user_upload/tx_pforum/',
    ])]
    protected ObjectStorage $images;

    public function __construct()
    {
        $this->posts = new ObjectStorage();
        $this->images = new ObjectStorage();
    }

    /**
     * Called again with initialize object, as fetching an entity from the DB does not use the constructor
     */
    public function initializeObject(): void
    {
        $this->posts ??= new ObjectStorage();
        $this->images ??= new ObjectStorage();
    }

    public function getHidden(): bool
    {
        return $this->hidden;
    }

    public function setHidden(bool $hidden): void
    {
        $this->hidden = $hidden;
    }

    public function getCrdate(): \DateTime
    {
        return $this->crdate;
    }

    public function setCrdate(\DateTime $crdate): void
    {
        $this->crdate = $crdate;
    }

    public function getForum(): ?Forum
    {
        return $this->forum;
    }

    public function setForum(Forum $forum): void
    {
        $this->forum = $forum;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = strip_tags($title);
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = strip_tags($description);
    }

    public function addPost(Post $post): void
    {
        $this->posts->attach($post);
    }

    public function removePost(Post $post): void
    {
        $this->posts->detach($post);
    }

    /**
     * @return ObjectStorage<Post>
     */
    public function getPosts(): ObjectStorage
    {
        return $this->posts;
    }

    /**
     * @param ObjectStorage<Post> $posts
     */
    public function setPosts(ObjectStorage $posts): void
    {
        $this->posts = $posts;
    }

    public function getAnonymousUser(): ?AnonymousUser
    {
        return $this->anonymousUser;
    }

    public function setAnonymousUser(AnonymousUser $anonymousUser): void
    {
        $this->anonymousUser = $anonymousUser;
    }

    public function getFrontendUser(): ?FrontendUser
    {
        return $this->frontendUser;
    }

    public function setFrontendUser(FrontendUser $frontendUser): void
    {
        $this->frontendUser = $frontendUser;
    }

    public function getHasValidUser(): bool
    {
        $frontendUserId = GeneralUtility::makeInstance(Context::class)
            ->getAspect('frontend.user')
            ->get('id');
        if ($frontendUserId > 0 && $this->frontendUser instanceof FrontendUser && $this->frontendUser->getUid() > 0) {
            return (int)$frontendUserId === $this->frontendUser->getUid();
        }

        return false;
    }

    /**
     * Helper method to get user.
     *
     * @return User|null $user
     */
    public function getUser(): ?User
    {
        if ($this->anonymousUser instanceof AnonymousUser) {
            $user = $this->getAnonymousUser();
        } elseif ($this->frontendUser instanceof FrontendUser) {
            $user = $this->getFrontendUser();
        } else {
            $user = null;
        }

        return $user;
    }

    /**
     * @return ObjectStorage<FileReference>
     */
    public function getImages(): ObjectStorage
    {
        return $this->images;
    }

    /**
     * @param ObjectStorage<FileReference> $images
     */
    public function setImages(ObjectStorage $images): void
    {
        $this->images = $images;
    }
}
