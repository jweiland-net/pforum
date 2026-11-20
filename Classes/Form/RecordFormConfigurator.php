<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Form;

use JWeiland\Pforum\Configuration\ExtConf;
use JWeiland\Pforum\Configuration\ForumSettings;
use JWeiland\Pforum\Service\RecordAccessService;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Adapts the form definition of a topic or post to the plugin settings and
 * to the record of the request, before the form is built
 */
readonly class RecordFormConfigurator
{
    private const USER_ELEMENTS = ['name', 'username', 'email'];

    private const UPLOAD_TYPES = ['FileUpload', 'ImageUpload'];

    public function __construct(
        private ExtConf $extConf,
    ) {}

    /**
     * @param array<string, mixed> $configuration Form definition
     * @return array<string, mixed>
     */
    public function configure(array $configuration, RecordFormContext $formContext): array
    {
        $configuration['renderingOptions']['controllerAction'] = $formContext->isNew() ? 'new' : 'edit';
        $configuration['renderingOptions']['additionalParams'][RecordAccessService::PLUGIN_NAMESPACE]
            = $formContext->getPluginArguments();

        $renderables = is_array($configuration['renderables'] ?? null) ? $configuration['renderables'] : [];
        $configuration['renderables'] = $this->configureRenderables($renderables, $formContext->settings);

        $finishers = is_array($configuration['finishers'] ?? null) ? $configuration['finishers'] : [];
        $configuration['finishers'] = $this->configureFinishers($finishers, $formContext);

        return $configuration;
    }

    /**
     * Removes the elements of the pforum user for frontend users and the
     * upload elements, if images are not used. Name and email get a
     * validator, if they are mandatory.
     *
     * @param array<int|string, mixed> $renderables
     * @return array<int|string, mixed>
     */
    private function configureRenderables(array $renderables, ForumSettings $settings): array
    {
        $mandatoryElements = array_keys(array_filter([
            'username' => $settings->usernameIsMandatory,
            'email' => $settings->emailIsMandatory,
        ]));

        foreach ($renderables as $key => $renderable) {
            if (!is_array($renderable) || $this->isRemoved($renderable, $settings)) {
                unset($renderables[$key]);
                continue;
            }

            if (in_array($renderable['identifier'] ?? '', $mandatoryElements, true)) {
                $renderable = $this->makeMandatory($renderable);
            }

            if (is_array($renderable['renderables'] ?? null)) {
                $renderable['renderables'] = $this->configureRenderables($renderable['renderables'], $settings);
            }

            $renderables[$key] = $renderable;
        }

        return array_values($renderables);
    }

    /**
     * @param array<string, mixed> $renderable
     */
    private function isRemoved(array $renderable, ForumSettings $settings): bool
    {
        return (!$settings->isAnonymousMode() && in_array($renderable['identifier'] ?? '', self::USER_ELEMENTS, true))
            || (!$settings->useImages && in_array($renderable['type'] ?? '', self::UPLOAD_TYPES, true));
    }

    /**
     * @param array<string, mixed> $renderable
     * @return array<string, mixed>
     */
    private function makeMandatory(array $renderable): array
    {
        $validators = is_array($renderable['validators'] ?? null) ? $renderable['validators'] : [];
        if (!in_array('NotEmpty', array_column($validators, 'identifier'), true)) {
            $validators[] = ['identifier' => 'NotEmpty'];
        }

        $renderable['validators'] = $validators;
        $renderable['properties']['fluidAdditionalAttributes']['required'] = 'required';

        return $renderable;
    }

    /**
     * @param array<int|string, mixed> $finishers
     * @return array<int|string, mixed>
     */
    private function configureFinishers(array $finishers, RecordFormContext $formContext): array
    {
        foreach ($finishers as $key => $finisher) {
            $identifier = is_array($finisher) ? (string)($finisher['identifier'] ?? '') : '';
            if ($identifier === 'EmailToSender' && (!$formContext->isNew() || !$formContext->settings->isMailToUserEnabled())) {
                unset($finishers[$key]);
                continue;
            }

            $finishers[$key] = match ($identifier) {
                'EmailToSender' => $this->configureEmailFinisher($finisher, $formContext),
                'FlashMessage' => $this->configureFlashMessageFinisher($finisher, $formContext),
                'Redirect' => $this->configureRedirectFinisher($finisher, $formContext),
                default => $finisher,
            };
        }

        return $finishers;
    }

    /**
     * The mail is sent to the pforum user, who created the record
     *
     * @param array<string, mixed> $finisher
     * @return array<string, mixed>
     */
    private function configureEmailFinisher(array $finisher, RecordFormContext $formContext): array
    {
        $options = is_array($finisher['options'] ?? null) ? $finisher['options'] : [];
        $finisher['options'] = array_replace(
            [
                'senderAddress' => $this->extConf->getEmailFromAddress(),
                'senderName' => $this->extConf->getEmailFromName(),
                'subject' => $this->translate('email.' . $formContext->recordType->value . '.subject'),
            ],
            array_filter($options),
        );
        $finisher['options']['variables']['pageUid'] = $formContext->pageUid;

        return $finisher;
    }

    /**
     * @param array<string, mixed> $finisher
     * @return array<string, mixed>
     */
    private function configureFlashMessageFinisher(array $finisher, RecordFormContext $formContext): array
    {
        $recordType = $formContext->recordType;
        $labelKey = match (true) {
            !$formContext->isNew() => $recordType->value . 'Updated',
            !$formContext->settings->isHiddenAtCreation($recordType) => $recordType->value . 'Created',
            $formContext->settings->isActivatedByAdmin($recordType) => 'hidden' . $recordType->getControllerName() . 'CreatedAndActivateByAdmin',
            default => 'hidden' . $recordType->getControllerName() . 'CreatedAndActivateByUser',
        };
        $finisher['options']['messageBody'] = $this->translate($labelKey);

        return $finisher;
    }

    /**
     * Shows the forum of the topic or the topic of the post after saving
     *
     * @param array<string, mixed> $finisher
     * @return array<string, mixed>
     */
    private function configureRedirectFinisher(array $finisher, RecordFormContext $formContext): array
    {
        if ((int)($finisher['options']['pageUid'] ?? 0) === 0) {
            $finisher['options']['pageUid'] = $formContext->pageUid;
        }

        $parentColumn = $formContext->recordType->getParentColumn();
        $finisher['options']['additionalParameters'] = http_build_query([
            RecordAccessService::PLUGIN_NAMESPACE => [
                'controller' => ucfirst($parentColumn),
                'action' => 'show',
                $parentColumn => $formContext->parentUid,
            ],
        ]);

        return $finisher;
    }

    private function translate(string $key): string
    {
        return (string)LocalizationUtility::translate($key, 'pforum');
    }
}
