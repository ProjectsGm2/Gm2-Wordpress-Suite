<?php

namespace Gm2\Content\Model;

final class Definition
{
    private string $slug;

    private string $singular;

    private string $plural;

    /**
     * @var array<string, string>
     */
    private array $labels;

    /**
     * @var string[]
     */
    private array $supports;

    private bool|string|null $hasArchive;

    private ?string $menuIcon;

    /**
     * @var array<string, mixed>
     */
    private array $rewrite;

    /**
     * @var string|string[]
     */
    private string|array $capabilityType;

    /**
     * @var string[]
     */
    private array $taxonomies;

    /**
     * @var array<string, mixed>
     */
    private array $arguments;

    /**
     * @param array<string, string> $labels
     * @param string[]              $supports
     * @param array<string, mixed>  $rewrite
     * @param string|string[]       $capabilityType
     * @param string[]              $taxonomies
     * @param array<string, mixed>  $arguments
     */
    public function __construct(
        string $slug,
        string $singular,
        string $plural,
        array $labels = [],
        array $supports = [],
        bool|string|null $hasArchive = null,
        ?string $menuIcon = null,
        array $rewrite = [],
        string|array $capabilityType = 'post',
        array $taxonomies = [],
        array $arguments = []
    ) {
        $this->slug           = $slug;
        $this->singular       = $singular;
        $this->plural         = $plural;
        $this->labels         = $labels;
        $this->supports       = array_values(array_unique(array_filter($supports, static fn ($support) => is_string($support))));
        $this->hasArchive     = $hasArchive;
        $this->menuIcon       = $menuIcon;
        $this->rewrite        = $rewrite;
        $this->capabilityType = $capabilityType;
        $this->taxonomies     = array_values(array_unique(array_filter($taxonomies, static fn ($taxonomy) => is_string($taxonomy))));
        $this->arguments      = $arguments;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getSingular(): string
    {
        return $this->singular;
    }

    public function getPlural(): string
    {
        return $this->plural;
    }

    /**
     * @return array<string, string>
     */
    public function getLabels(): array
    {
        return $this->labels;
    }

    /**
     * @return string[]
     */
    public function getSupports(): array
    {
        return $this->supports;
    }

    public function getHasArchive(): bool|string|null
    {
        return $this->hasArchive;
    }

    public function getMenuIcon(): ?string
    {
        return $this->menuIcon;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRewrite(): array
    {
        return $this->rewrite;
    }

    /**
     * @return string|string[]
     */
    public function getCapabilityType(): string|array
    {
        return $this->capabilityType;
    }

    /**
     * @return string[]
     */
    public function getTaxonomies(): array
    {
        return $this->taxonomies;
    }

    /**
     * @return array<string, mixed>
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }
}
