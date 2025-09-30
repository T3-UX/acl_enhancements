<?php

declare(strict_types=1);

/*
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

namespace T3UX\AclEnhancements\Utility;

use T3UX\AclEnhancements\Writer\YamlPermissionSetsWriter;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class YamlFileUtility
{
    /**
     * @return non-empty-string
     */
    public static function normalizeIdentifierFromFilename(string $filename): string
    {
        return YamlPermissionSetsWriter::PROVIDER_PREFIX . '/' . YamlPermissionSetsWriter::DEFAULT_PACKAGE . '/'
            . str_replace('_', '-', $filename);
    }

    public static function translateIdentifierToFilename(string $identifier): string
    {
        $identifierParts = explode('/', $identifier);
        return array_pop($identifierParts);
    }

    public static function getIdentifierFromPath(string $path): string
    {
        return trim(str_replace([self::getDefaultPath(), '.' . YamlPermissionSetsWriter::FILE_EXTENSION], '', $path), '/');
    }

    public static function sanitizeFilename(string $name): string
    {
        $string = trim($name);

        if (function_exists('transliterator_transliterate')) {
            $string = transliterator_transliterate('Any-Latin; Latin-ASCII', $string);
        } elseif (function_exists('iconv')) {
            $string = (string)@iconv('UTF-8', 'ASCII//TRANSLIT', $string);
        }

        $string = strtolower((string)$string);
        $string = preg_replace('/[^a-z0-9\-_]+/', '-', $string) ?? '';
        $string = trim($string, '-_');

        return (int)preg_match('/^[a-z0-9\-_]+$/', $string) > 0 ? $string : '';
    }

    public static function getDefaultPath(): string
    {
        return rtrim(Environment::getConfigPath(), '/') . '/' . trim(YamlPermissionSetsWriter::DEFAULT_SUB_DIR, '/');
    }

    public static function getPermissionsDirectory(): string
    {
        $dir = self::getDefaultPath();

        if (!GeneralUtility::isAllowedAbsPath($dir)) {
            throw new \Exception('Directory outside of project.');
        }

        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Cannot create directory: ' . $dir);
        }

        return $dir;
    }

    public static function uniqueFilenameInPath(string $filename, string $path): string
    {
        $filePath = rtrim($path, '/') . '/' . $filename;

        if (!file_exists($filePath)) {
            if (!GeneralUtility::isAllowedAbsPath($filePath)) {
                throw new \Exception('Directory outside of project.');
            }

            return $filePath;
        }

        $dir = dirname($filePath);
        $base = pathinfo($filePath, PATHINFO_FILENAME);

        $i = 1;

        do {
            $filePath = $dir . '/' . $base . '-' . $i . '.' . YamlPermissionSetsWriter::FILE_EXTENSION;
            $i++;
        } while (file_exists($filePath));

        if (!GeneralUtility::isAllowedAbsPath($dir)) {
            throw new \Exception('Directory outside of project.');
        }

        return $filePath;
    }
}
