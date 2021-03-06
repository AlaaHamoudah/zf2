<?php
/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_File
 * @copyright  Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */

namespace Zend\File;

// import SPL classes/interfaces into local scope
use DirectoryIterator,
    FilterIterator,
    RecursiveIterator,
    RecursiveDirectoryIterator,
    RecursiveIteratorIterator;

/**
 * Locate files containing PHP classes, interfaces, or abstracts
 *
 * @category   Zend
 * @package    Zend_File
 * @copyright  Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class ClassFileLocator extends FilterIterator
{
    /**
     * Create an instance of the locator iterator
     * 
     * Expects either a directory, or a DirectoryIterator (or its recursive variant) 
     * instance.
     * 
     * @param  string|DirectoryIterator $dirOrIterator
     */
    public function __construct($dirOrIterator = '.')
    {
        if (is_string($dirOrIterator)) {
            if (!is_dir($dirOrIterator)) {
                throw new Exception\InvalidArgumentException('Expected a valid directory name');
            }

            $dirOrIterator = new RecursiveDirectoryIterator($dirOrIterator, RecursiveDirectoryIterator::FOLLOW_SYMLINKS);
        }
        if (!$dirOrIterator instanceof DirectoryIterator) {
            throw new Exception\InvalidArgumentException('Expected a DirectoryIterator');
        }

        if ($dirOrIterator instanceof RecursiveIterator) {
            $iterator = new RecursiveIteratorIterator($dirOrIterator);
        } else {
            $iterator = $dirOrIterator;
        }

        parent::__construct($iterator);
    }

    /**
     * Filter for files containing PHP classes, interfaces, or abstracts
     * 
     * @return bool
     */
    public function accept()
    {
        $file = $this->getInnerIterator()->current();

        // If we somehow have something other than an SplFileInfo object, just 
        // return false
        if (!$file instanceof \SplFileInfo) {
            return false;
        }

        // If we have a directory, it's not a file, so return false
        if (!$file->isFile()) {
            return false;
        }

        // If not a PHP file, skip
        if ($file->getBasename('.php') == $file->getBasename()) {
            return false;
        }

        $contents = file_get_contents($file->getRealPath());
        $tokens   = token_get_all($contents);
        $count    = count($tokens);
        $i        = 0;
        while ($i < $count) {
            $token = $tokens[$i];

            if (!is_array($token)) {
                // single character token found; skip
                $i++;
                continue;
            }

            list($id, $content, $line) = $token;

            switch ($id) {
                case T_NAMESPACE:
                    // Namespace found; grab it for later
                    $namespace = '';
                    $done      = false;
                    do {
                        ++$i;
                        if (!array_key_exists($i, $tokens)) {
                            break;
                        }
                        $token = $tokens[$i];
                        if (is_string($token)) {
                            if (';' === $token) {
                                $done = true;
                            }
                            continue;
                        }
                        list($type, $content, $line) = $token;
                        switch ($type) {
                            case T_STRING:
                            case T_NS_SEPARATOR:
                                $namespace .= $content;
                                break;
                        }
                    } while (!$done && $i < $count);

                    // Set the namespace of this file in the object
                    $file->namespace = $namespace;
                    break;
                case T_CLASS:
                case T_INTERFACE:
                    // Abstract class, class, or interface found

                    // Get the classname
                    $class = '';
                    do {
                        ++$i;
                        $token = $tokens[$i];
                        if (is_string($token)) {
                            continue;
                        }
                        list($type, $content, $line) = $token;
                        switch ($type) {
                            case T_STRING:
                                $class = $content;
                                break;
                        }
                    } while (empty($class) && $i < $count);

                    // If a classname was found, set it in the object, and 
                    // return boolean true (found)
                    if (!empty($class)) {
                        $file->classname = $class;
                        return true;
                    }
                    break;
                default:
                    break;
            }
            ++$i;
        }

        // No class-type tokens found; return false
        return false;
    }
}
