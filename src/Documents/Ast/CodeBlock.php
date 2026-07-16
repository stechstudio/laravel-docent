<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Ast;

final class CodeBlock extends Node
{
    public function __construct(
        public string $code,
        public ?string $language = null,
        public ?string $info = null,
        ?int $line = null,
    ) {
        parent::__construct($line);
    }

    /**
     * A `filename` hint may be encoded in the fence info string as
     * `language filename="app/Foo.php"`.
     */
    public function filename(): ?string
    {
        if ($this->info === null) {
            return null;
        }

        if (preg_match('/filename=(?:"([^"]*)"|(\S+))/', $this->info, $m) === 1) {
            return $m[1] !== '' ? $m[1] : ($m[2] ?? null);
        }

        return null;
    }

    public function title(): ?string
    {
        if ($this->info !== null && preg_match('/title=(?:"([^"]*)"|(\S+))/', $this->info, $m) === 1) {
            return $m[1] !== '' ? $m[1] : ($m[2] ?? null);
        }

        return null;
    }

    public function label(): string
    {
        return $this->filename() ?? $this->title() ?? ($this->language !== null && $this->language !== ''
            ? ucfirst($this->language)
            : 'Code');
    }
}
