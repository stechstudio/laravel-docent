<?php

declare(strict_types=1);

namespace STS\Docent\Content;

/**
 * The Diátaxis content types Docent can scaffold. Each case carries a starter
 * page skeleton whose front matter and section outline encode that type's shape,
 * so `docent:make` gives authors (and coding agents) the right structure to fill
 * in rather than a blank file.
 */
enum ContentType: string
{
    case Tutorial = 'tutorial';
    case HowTo = 'how-to';
    case Reference = 'reference';
    case Concept = 'concept';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $t): string => $t->value, self::cases());
    }

    public function scaffold(string $title): string
    {
        return match ($this) {
            self::Tutorial => <<<MD
                ---
                title: {$title}
                description: A learning-oriented walkthrough of what the reader will build.
                ---

                ## What you'll build

                One or two sentences on the end result and who this is for.

                ## Before you start

                - A prerequisite
                - Another prerequisite

                ## Steps

                ::::steps
                :::step Do the first thing
                Explain the first action and what the reader should see.
                :::
                :::step Do the next thing
                Continue the walkthrough to the finished result.
                :::
                ::::

                ## Recap

                What the reader accomplished, and where to go next.
                MD,
            self::HowTo => <<<MD
                ---
                title: {$title}
                description: A task-oriented guide to accomplishing a single goal.
                ---

                ## Goal

                State the one task this guide accomplishes.

                ## Before you start

                - What the reader needs in place first

                ## Steps

                ::::steps
                :::step First action
                The concrete step to take.
                :::
                :::step Second action
                The next concrete step.
                :::
                ::::

                ## Result

                How the reader confirms it worked.
                MD,
            self::Reference => <<<MD
                ---
                title: {$title}
                description: An information-oriented reference for looking things up.
                ---

                State what this reference documents. Reference pages are for
                lookup, not learning — keep prose minimal and structure scannable.

                ## Overview

                One or two sentences of orientation.

                ## Details

                | Name | Type | Description |
                | ---- | ---- | ----------- |
                | example | string | What it is. |

                ## Notes

                Edge cases, defaults, and gotchas worth recording.
                MD,
            self::Concept => <<<MD
                ---
                title: {$title}
                description: An understanding-oriented explanation of an idea.
                ---

                ## What it is

                Define the concept in one or two sentences.

                ## How it works

                Explain the mechanism — how the pieces fit together.

                ## Why it matters

                When this concept is relevant and what it lets the reader do.

                ## See also

                Point to related pages so the reader can go deeper.
                MD,
        };
    }
}
