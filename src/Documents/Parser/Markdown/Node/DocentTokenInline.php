<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Parser\Markdown\Node;

use League\CommonMark\Node\Inline\AbstractInline;
use STS\Docent\Documents\Ast\AppLink;
use STS\Docent\Documents\Ast\DynamicValue;
use STS\Docent\Documents\Ast\Node as AstNode;

/**
 * Wraps an already-built Docent inline node ({@see DynamicValue}
 * or {@see AppLink}) so it can ride along in the CommonMark tree
 * until AST conversion.
 */
final class DocentTokenInline extends AbstractInline
{
    public function __construct(
        public readonly AstNode $node,
    ) {
        parent::__construct();
    }
}
