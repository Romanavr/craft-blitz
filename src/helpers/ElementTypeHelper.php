<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\helpers;

use Craft;
use craft\elements\Entry;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\events\RegisterNonCacheableElementTypesEvent;
use yii\base\Event;

class ElementTypeHelper
{
    // Constants
    // =========================================================================

    /**
     * @event RegisterNonCacheableElementTypesEvent
     */
    const EVENT_REGISTER_NON_CACHEABLE_ELEMENT_TYPES = 'registerNonCacheableElementTypes';

    /**
     * @const string[]
     */
    const NON_CACHEABLE_ELEMENT_TYPES = [
        'craft\elements\GlobalSet',
        'craft\elements\MatrixBlock',
        'benf\neo\elements\Block',
        'verbb\supertable\elements\SuperTableBlockElement',
        'putyourlightson\campaign\elements\ContactElement',
    ];

    // Properties
    // =========================================================================

    /**
     * @var string[]|null
     */
    private static $_nonCacheableElementTypes;

    // Static
    // =========================================================================

    /**
     * Returns whether the element type is cacheable.
     *
     * @param string $elementType
     *
     * @return bool
     */
    public static function getIsCacheableElementType(string $elementType): bool
    {
        // Don't proceed if this is a non cacheable element type
        if (in_array($elementType, self::getNonCacheableElementTypes(), true)) {
            return false;
        }

        return true;
    }

    /**
     * Returns non cacheable element types.
     *
     * @return string[]
     */
    public static function getNonCacheableElementTypes(): array
    {
        if (self::$_nonCacheableElementTypes !== null) {
            return self::$_nonCacheableElementTypes;
        }

        $event = new RegisterNonCacheableElementTypesEvent([
            'elementTypes' => Blitz::$plugin->settings->nonCacheableElementTypes,
        ]);
        Event::trigger(self::class, self::EVENT_REGISTER_NON_CACHEABLE_ELEMENT_TYPES, $event);

        self::$_nonCacheableElementTypes = $event->elementTypes;

        return self::$_nonCacheableElementTypes;
    }
}
