<?php

declare(strict_types=1);

/*
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

namespace T3UX\AclEnhancements\Permission\Set\Exception;

use T3UX\AclEnhancements\Permission\ApplyMode;
use T3UX\AclEnhancements\Permission\RecordPermissionSetsApplyHandler;
use TYPO3\CMS\Core\Exception;

/**
 * {@see RecordPermissionSetsApplyHandler::apply()} throw this exception when tried to apply set(s)
 * in {@see ApplyMode::PERSIST} mode, but record has selected dynamic sets set.
 *
 * @internal This is an experimental implementation and might change in TYPO3 v14.
 */
class PermissionSetCannotBeAppliedForRecordPersistingException extends Exception {}
