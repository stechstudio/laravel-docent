<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Ast;

/**
 * Base class for every Docent AST node.
 *
 * Nodes are pure data — no rendering logic lives here. They must remain
 * safely `serialize()`-able so parsed documents can be cached.
 */
abstract class Node
{
    /** @var list<Node> */
    public array $children = [];

    public function __construct(public ?int $line = null) {}

    public function appendChild(Node $child): void
    {
        $this->children[] = $child;
    }

    /**
     * @param  iterable<Node>  $children
     */
    public function setChildren(iterable $children): void
    {
        $this->children = is_array($children)
            ? array_values($children)
            : iterator_to_array($children, false);
    }

    public function hasChildren(): bool
    {
        return $this->children !== [];
    }
}
