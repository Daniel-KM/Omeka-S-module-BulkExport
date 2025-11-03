<?php declare(strict_types=1);

namespace BulkExport\Api\Representation;

use DateTime;
use Omeka\Api\Representation\AbstractEntityRepresentation;

class ShaperRepresentation extends AbstractEntityRepresentation
{
    public function getControllerName()
    {
        return 'shaper';
    }

    public function getJsonLdType()
    {
        return 'o-bulk:Shaper';
    }

    public function getJsonLd()
    {
        $owner = $this->owner();

        $getDateTimeJsonLd = function (?\DateTime $dateTime): ?array {
            return $dateTime
            ? [
                '@value' => $dateTime->format('c'),
                '@type' => 'http://www.w3.org/2001/XMLSchema#dateTime',
            ]
            : null;
        };

        return [
            'o:id' => $this->id(),
            'o:owner' => $owner ? $owner->getReference()->jsonSerialize() : null,
            'o:label' => $this->label(),
            'o:config' => $this->config(),
            'o:created' => $getDateTimeJsonLd($this->resource->getCreated()),
            'o:modified' => $getDateTimeJsonLd($this->resource->getModified()),
        ];
    }

    public function getResource(): \BulkExport\Entity\Shaper
    {
        return $this->resource;
    }

    public function owner(): ?\Omeka\Api\Representation\UserRepresentation
    {
        $user = $this->resource->getOwner();
        return $user
            ? $this->getAdapter('users')->getRepresentation($user)
            : null;
    }

    public function label(): string
    {
        return $this->resource->getLabel();
    }

    public function config(): array
    {
        return $this->resource->getConfig();
    }

    public function configOption(string $part, $key)
    {
        $conf = $this->resource->getConfig();
        return $conf[$part][$key] ?? null;
    }

    public function created(): \DateTime
    {
        return $this->resource->getCreated();
    }

    public function modified(): ?\DateTime
    {
        return $this->resource->getModified();
    }

    public function adminUrl($action = null, $canonical = false)
    {
        $url = $this->getViewHelper('Url');
        return $url(
            'admin/bulk-export/id',
            [
                'controller' => $this->getControllerName(),
                'action' => $action,
                'id' => $this->id(),
            ],
            ['force_canonical' => $canonical]
        );
    }

    /**
     * Get the display title for this resource.
     *
     * @param string|null $default
     * @param array|string|null $lang
     * @return string|null
     *
     * @see \Omeka\Api\Representation\AbstractResourceRepresentation::displayTitle()
     */
    public function displayTitle($default = null, $lang = null)
    {
        $title = $this->label();
        if ($title === null || $title === '') {
            if ($default === null || $default === '') {
                $translator = $this->getServiceLocator()->get('MvcTranslator');
                $title = sprintf(
                    $translator->translate('Shaper #%d'), // @translate
                    $this->id()
                );
            } else {
                $title = $default;
            }
        }
        return $title;;
    }

    /**
     * Get a "pretty" link to this resource containing a thumbnail and
     * display title.
     *
     * @param string $thumbnailType Type of thumbnail to show
     * @param string|null $titleDefault See $default param for displayTitle()
     * @param string|null $action Action to link to (see link() and linkRaw())
     * @param array $attributes HTML attributes, key and value
     * @param array|string|null $lang Language IETF tag
     * @return string
     *
     * @see \Omeka\Api\Representation\AbstractResourceRepresentation::linkPretty()
     */
    public function linkPretty(
        $thumbnailType = 'square',
        $titleDefault = null,
        $action = null,
        array $attributes = null,
        $lang = null
    ) {
        $escape = $this->getViewHelper('escapeHtml');
        $thumbnail = $this->getViewHelper('thumbnail');
        $linkContent = sprintf(
            '%s<span class="resource-name">%s</span>',
            $thumbnail($this, $thumbnailType),
            $escape($this->displayTitle($titleDefault, $lang))
        );
        if (empty($attributes['class'])) {
            $attributes['class'] = 'resource-link';
        } else {
            $attributes['class'] .= ' resource-link';
        }
        return $this->linkRaw($linkContent, $action, $attributes);
    }
}
