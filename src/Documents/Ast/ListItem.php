<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Ast;

final class ListItem extends Node
{
    /**
     * @param  ?bool  $checked  Task-list state: null = not a task, true/false = checkbox state.
     */
    public function __construct(
        public ?bool $checked = null,
        ?int $line = null,
    ) {
        parent::__construct($line);
    }
}
