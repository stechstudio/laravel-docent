<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Renderer;

use STS\Docent\Documents\Ast\AudienceBlock;
use STS\Docent\Documents\Ast\AuthorizationBlock;
use STS\Docent\Documents\Ast\ConditionBlock;
use STS\Docent\Runtime\DocumentationContext;
use STS\Docent\Runtime\IntegrationRegistry;

/**
 * Shared context-aware visibility decisions for conditional AST blocks. Used by
 * every context-aware renderer so authorization/condition/audience gating stays
 * identical across HTML and plain-text output.
 */
trait ResolvesVisibility
{
    protected function authorizationVisible(AuthorizationBlock $node, DocumentationContext $context): bool
    {
        return $node->mode->grants($context->can($node->ability, $node->arguments));
    }

    protected function conditionVisible(ConditionBlock $node, IntegrationRegistry $registry, DocumentationContext $context): bool
    {
        $result = $registry->resolveCondition($node->condition, $context);

        // Unknown condition → render nothing.
        if ($result === null) {
            return false;
        }

        return $node->negated ? ! $result : $result;
    }

    protected function audienceVisible(AudienceBlock $node, IntegrationRegistry $registry, DocumentationContext $context): bool
    {
        // A preview override forces a single audience's perspective.
        if ($context->audience !== null) {
            return $context->audience === $node->audience;
        }

        return $registry->resolveAudience($node->audience, $context) ?? false;
    }
}
