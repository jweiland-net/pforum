<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Mail;

use JWeiland\Pforum\Configuration\ExtConf;
use JWeiland\Pforum\Converter\RteContentConverter;
use JWeiland\Pforum\Form\RecordType;
use JWeiland\Pforum\Service\PluginSettingsService;
use JWeiland\Pforum\Service\RecordService;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\Mime\Address;
use TYPO3\CMS\Core\Mail\FluidEmail;
use TYPO3\CMS\Core\Mail\MailerInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Fluid\View\TemplatePaths;

/**
 * Informs the author of a topic about a new visible post of the topic.
 * The template root paths of TypoScript "settings.email.templateRootPaths"
 * are added to this mail only, so the template is not available for other
 * mails of the installation.
 */
readonly class NewPostMail
{
    public const TEMPLATE_NAME = 'PforumNewPost';

    public function __construct(
        private RecordService $recordService,
        private PluginSettingsService $pluginSettingsService,
        private RteContentConverter $rteContentConverter,
        private ExtConf $extConf,
        private MailerInterface $mailer,
    ) {}

    public function send(int $postUid, ?ServerRequestInterface $request = null): void
    {
        $post = $this->recordService->findRecord(RecordType::Post, $postUid);
        $topic = $this->recordService->findRecord(RecordType::Topic, (int)($post['topic'] ?? 0));
        $author = $this->getAuthor($topic);
        if ($post === [] || !GeneralUtility::validEmail($author['email'])) {
            return;
        }

        $email = $this->createEmail($author, $topic, $post, $this->getTemplatePaths($request));
        if ($request instanceof ServerRequestInterface) {
            $email->setRequest($request);
        }

        $this->mailer->send($email);
    }

    /**
     * @param array{email: string, name: string} $author
     * @param array<string, mixed> $topic
     * @param array<string, mixed> $post
     */
    private function createEmail(array $author, array $topic, array $post, TemplatePaths $templatePaths): FluidEmail
    {
        $description = $this->getDescriptionAsHtml((string)$post['description']);
        $email = GeneralUtility::makeInstance(FluidEmail::class, $templatePaths);
        $email
            ->to(new Address($author['email'], $author['name']))
            ->from(new Address($this->extConf->getEmailFromAddress(), $this->extConf->getEmailFromName()))
            ->subject(sprintf((string)LocalizationUtility::translate('email.newPost.subject', 'pforum'), $topic['title']))
            ->setTemplate(self::TEMPLATE_NAME)
            ->assignMultiple([
                'author' => $author,
                'topic' => $topic,
                'post' => $post,
                'description' => $description,
                'descriptionPlainText' => $this->rteContentConverter->convertHtmlToPlainText($description),
            ]);

        return $email;
    }

    /**
     * @param array<string, mixed> $topic
     * @return array{email: string, name: string}
     */
    private function getAuthor(array $topic): array
    {
        $user = (int)($topic['frontend_user'] ?? 0) > 0
            ? $this->recordService->findFrontendUser((int)$topic['frontend_user'])
            : $this->recordService->findUser((int)($topic['anonymous_user'] ?? 0));

        return [
            'email' => (string)($user['email'] ?? ''),
            'name' => (string)($user['name'] ?? ''),
        ];
    }

    /**
     * Descriptions of former versions of pforum are plain text
     */
    private function getDescriptionAsHtml(string $description): string
    {
        return trim(strip_tags($description)) === trim($description)
            ? $this->rteContentConverter->convertPlainTextToHtml($description)
            : $description;
    }

    private function getTemplatePaths(?ServerRequestInterface $request): TemplatePaths
    {
        $settings = $this->pluginSettingsService->getSettings($request);
        $ownTemplateRootPaths = $settings['email']['templateRootPaths'] ?? [];
        $ownTemplateRootPaths = is_array($ownTemplateRootPaths) ? $ownTemplateRootPaths : [];
        ksort($ownTemplateRootPaths);

        $templatePaths = new TemplatePaths();
        $templatePaths->setTemplateRootPaths([
            ...array_values($GLOBALS['TYPO3_CONF_VARS']['MAIL']['templateRootPaths'] ?? []),
            ...array_values($ownTemplateRootPaths),
        ]);
        $templatePaths->setLayoutRootPaths($GLOBALS['TYPO3_CONF_VARS']['MAIL']['layoutRootPaths'] ?? []);
        $templatePaths->setPartialRootPaths($GLOBALS['TYPO3_CONF_VARS']['MAIL']['partialRootPaths'] ?? []);

        return $templatePaths;
    }
}
