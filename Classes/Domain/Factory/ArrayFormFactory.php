<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Domain\Factory;

use JWeiland\Pforum\Form\FormOwner;
use JWeiland\Pforum\Form\RecordFormConfigurator;
use JWeiland\Pforum\Form\RecordFormContext;
use JWeiland\Pforum\Form\RecordFormContextBuilder;
use JWeiland\Pforum\Form\RecordFormPrefiller;
use JWeiland\Pforum\Form\RecordType;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Form\Domain\Factory\ArrayFormFactory as CoreArrayFormFactory;
use TYPO3\CMS\Form\Domain\Model\FormDefinition;

/**
 * Builds the topic and post forms based on EXT:form. The rendering option
 * "recordType" of the form definition defines, whether a topic or a post
 * is created or edited. The form definition is configured for the current
 * request before it is built (form action, elements and finishers).
 * Afterward, the elements are prefilled with the values of the record to
 * edit. The rendering option "formOwner" marks the form, so the hooks of
 * pforum act on forms of this factory only (see FormOwner).
 *
 * Usage in Fluid:
 * <formvh:render persistenceIdentifier="EXT:pforum/Configuration/Form/Topic.form.yaml"
 *                factoryClass="JWeiland\Pforum\Domain\Factory\ArrayFormFactory"/>
 */
#[Autoconfigure(public: true, shared: false)]
class ArrayFormFactory extends CoreArrayFormFactory
{
    public function __construct(
        protected readonly RecordFormContextBuilder $recordFormContextBuilder,
        protected readonly RecordFormConfigurator $recordFormConfigurator,
        protected readonly RecordFormPrefiller $recordFormPrefiller,
    ) {}

    /**
     * @param array<string, mixed> $configuration
     */
    public function build(
        array $configuration,
        ?string $prototypeName = null,
        ?ServerRequestInterface $request = null,
    ): FormDefinition {
        $configuration = FormOwner::markConfiguration($configuration);
        $recordType = RecordType::fromConfiguration($configuration);
        $formContext = $recordType instanceof RecordType && $request instanceof ServerRequestInterface
            ? $this->recordFormContextBuilder->build($recordType, $request)
            : null;

        if ($formContext instanceof RecordFormContext) {
            $configuration = $this->recordFormConfigurator->configure($configuration, $formContext);
        }

        $formDefinition = parent::build($configuration, $prototypeName, $request);
        if ($formContext instanceof RecordFormContext) {
            $this->recordFormPrefiller->prefill($formDefinition, $formContext);
        }

        return $formDefinition;
    }
}
