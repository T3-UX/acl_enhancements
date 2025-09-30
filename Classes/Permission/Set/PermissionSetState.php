<?php

declare(strict_types=1);

/*
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

namespace T3UX\AclEnhancements\Permission\Set;

/**
 * Defines the state of a {@see PermissionSetInterface}.
 *
 * @internal This is an experimental implementation and might change in TYPO3 v14.
 */
enum PermissionSetState: string
{
    /**
     * Permission set is valid.
     */
    case VALID = 'valid';

    /**
     * No data loaded for permission preset.
     */
    case NO_DATA = 'noData';

    /**
     * Permission set invalid, error occurred during loading source data.
     */
    case LOADING_ERROR = 'loadingError';

    /**
     * Permission set invalid, unknown error occurred or other source of invalidation.
     */
    case ERROR = 'error';

    /**
     * Determine if current state is valid.
     */
    public function isValid(): bool
    {
        return $this === self::VALID;
    }
}
