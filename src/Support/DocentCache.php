<?php

declare(strict_types=1);

namespace STS\Docent\Support;

use Closure;
use Illuminate\Contracts\Cache\Repository;
use STS\Docent\Content\PageReference;
use STS\Docent\Documents;
use STS\Docent\Search\SearchRecord;

/**
 * Versioned, store-agnostic cache for parsed ASTs, navigation, and search.
 *
 * Every key is namespaced with a version stamp; `docent:clear` bumps the stamp,
 * orphaning (and thereby invalidating) all prior entries without needing cache
 * tags or a full flush.
 *
 * Values are serialized by Docent itself and stored as strings, because
 * modern Laravel apps ship `cache.serializable_classes => false` and refuse
 * to unserialize objects out of cache. Reads unserialize against an explicit
 * allowlist of Docent's own pure-data classes, preserving the framework's
 * gadget-chain protection without requiring any host configuration.
 */
final class DocentCache
{
    /**
     * Every class that may appear in a cached value. All are final pure-data
     * classes (or enums) with no magic methods. A test reflects over the AST
     * namespace to keep this list complete.
     */
    public const ALLOWED_CLASSES = [
        PageReference::class,
        SearchRecord::class,
        Documents\Document::class,
        Documents\FrontMatter::class,
        Documents\Ast\AppLink::class,
        Documents\Ast\AppLinkKind::class,
        Documents\Ast\Accordion::class,
        Documents\Ast\AudienceBlock::class,
        Documents\Ast\AuthorizationBlock::class,
        Documents\Ast\AuthorizationMode::class,
        Documents\Ast\BlockQuote::class,
        Documents\Ast\BulletList::class,
        Documents\Ast\Callout::class,
        Documents\Ast\CalloutType::class,
        Documents\Ast\Card::class,
        Documents\Ast\CardGroup::class,
        Documents\Ast\CodeBlock::class,
        Documents\Ast\CodeGroup::class,
        Documents\Ast\ComponentNode::class,
        Documents\Ast\ConditionBlock::class,
        Documents\Ast\DynamicValue::class,
        Documents\Ast\Emphasis::class,
        Documents\Ast\Frame::class,
        Documents\Ast\HardBreak::class,
        Documents\Ast\Heading::class,
        Documents\Ast\HtmlBlock::class,
        Documents\Ast\HtmlInline::class,
        Documents\Ast\Image::class,
        Documents\Ast\IncludeNode::class,
        Documents\Ast\InlineCode::class,
        Documents\Ast\Link::class,
        Documents\Ast\ListItem::class,
        Documents\Ast\Node::class,
        Documents\Ast\OrderedList::class,
        Documents\Ast\Paragraph::class,
        Documents\Ast\SoftBreak::class,
        Documents\Ast\Step::class,
        Documents\Ast\Steps::class,
        Documents\Ast\Strikethrough::class,
        Documents\Ast\Strong::class,
        Documents\Ast\Table::class,
        Documents\Ast\TableCell::class,
        Documents\Ast\TableRow::class,
        Documents\Ast\TableSection::class,
        Documents\Ast\Tab::class,
        Documents\Ast\Tabs::class,
        Documents\Ast\Text::class,
        Documents\Ast\ThematicBreak::class,
        Documents\Ast\Video::class,
    ];

    public function __construct(
        private readonly Repository $store,
        private readonly string $prefix = 'docent',
    ) {}

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function remember(string $key, Closure $callback): mixed
    {
        $qualified = $this->key($key);
        $cached = $this->store->get($qualified);

        if (is_string($cached) && $this->payloadIsAllowed($cached)) {
            $value = @unserialize($cached, ['allowed_classes' => self::ALLOWED_CLASSES]);

            if ($value !== false) {
                return $value;
            }
        }

        $value = $callback();

        $this->store->forever($qualified, serialize($value));

        return $value;
    }

    public function version(): int
    {
        return (int) $this->store->get($this->prefix.':version', 1);
    }

    public function bump(): void
    {
        $this->store->forever($this->prefix.':version', $this->version() + 1);
    }

    /**
     * A payload referencing any class outside the allowlist is treated as a
     * cache miss and recomputed, rather than unserializing to
     * __PHP_Incomplete_Class and failing later at a type hint. Enum payloads
     * (`E:`) carry a `Class:case` token, so the case suffix is stripped.
     */
    private function payloadIsAllowed(string $payload): bool
    {
        preg_match_all('/[OCE]:\d+:"([^"]+)"/', $payload, $matches);

        foreach ($matches[1] as $class) {
            $class = str_contains($class, ':') ? strstr($class, ':', true) : $class;

            if (! in_array($class, self::ALLOWED_CLASSES, true)) {
                return false;
            }
        }

        return true;
    }

    private function key(string $suffix): string
    {
        return $this->prefix.':'.$this->version().':'.$suffix;
    }
}
