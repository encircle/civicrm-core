<?php
/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.5                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2014                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*/

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2014
 * $Id: $
 *
 */

/**
 * class to provide simple static functions for file objects
 */
class CRM_Utils_File {

  /**
   * Given a file name, determine if the file contents make it an ascii file
   *
   * @param string $name name of file
   *
   * @return boolean     true if file is ascii
   * @access public
   */
  static function isAscii($name) {
    $fd = fopen($name, "r");
    if (!$fd) {
      return FALSE;
    }

    $ascii = TRUE;
    while (!feof($fd)) {
      $line = fgets($fd, 8192);
      if (!CRM_Utils_String::isAscii($line)) {
        $ascii = FALSE;
        break;
      }
    }

    fclose($fd);
    return $ascii;
  }

  /**
   * Given a file name, determine if the file contents make it an html file
   *
   * @param string $name name of file
   *
   * @return boolean     true if file is html
   * @access public
   */
  static function isHtml($name) {
    $fd = fopen($name, "r");
    if (!$fd) {
      return FALSE;
    }

    $html = FALSE;
    $lineCount = 0;
    while (!feof($fd) & $lineCount <= 5) {
      $lineCount++;
      $line = fgets($fd, 8192);
      if (!CRM_Utils_String::isHtml($line)) {
        $html = TRUE;
        break;
      }
    }

    fclose($fd);
    return $html;
  }

  /**
   * create a directory given a path name, creates parent directories
   * if needed
   *
   * @param string $path  the path name
   * @param boolean $abort should we abort or just return an invalid code
   *
   * @return void
   * @access public
   * @static
   */
  static function createDir($path, $abort = TRUE) {
    if (is_dir($path) || empty($path)) {
      return;
    }

    CRM_Utils_File::createDir(dirname($path), $abort);
    if (@mkdir($path, 0777) == FALSE) {
      if ($abort) {
        $docLink = CRM_Utils_System::docURL2('Moving an Existing Installation to a New Server or Location', NULL, NULL, NULL, NULL, "wiki");
        echo "Error: Could not create directory: $path.<p>If you have moved an existing CiviCRM installation from one location or server to another there are several steps you will need to follow. They are detailed on this CiviCRM wiki page - {$docLink}. A fix for the specific problem that caused this error message to be displayed is to set the value of the config_backend column in the civicrm_domain table to NULL. However we strongly recommend that you review and follow all the steps in that document.</p>";

        CRM_Utils_System::civiExit();
      }
      else {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * delete a directory given a path name, delete children directories
   * and files if needed
   *
   * @param $target
   * @param bool $rmdir
   * @param bool $verbose
   *
   * @throws Exception
   * @internal param string $path the path name
   *
   * @return void
   * @access public
   * @static
   */
  static function cleanDir($target, $rmdir = TRUE, $verbose = TRUE) {
    static $exceptions = array('.', '..');
    if ($target == '' || $target == '/') {
      throw new Exception("Overly broad deletion");
    }

    if ($sourcedir = @opendir($target)) {
      while (FALSE !== ($sibling = readdir($sourcedir))) {
        if (!in_array($sibling, $exceptions)) {
          $object = $target . DIRECTORY_SEPARATOR . $sibling;

          if (is_dir($object)) {
            CRM_Utils_File::cleanDir($object, $rmdir, $verbose);
          }
          elseif (is_file($object)) {
            if (!unlink($object)) {
              CRM_Core_Session::setStatus(ts('Unable to remove file %1', array(1 => $object)), ts('Warning'), 'error');
          }
        }
      }
      }
      closedir($sourcedir);

      if ($rmdir) {
        if (rmdir($target)) {
          if ($verbose) {
            CRM_Core_Session::setStatus(ts('Removed directory %1', array(1 => $target)), '', 'success');
          }
          return TRUE;
      }
        else {
          CRM_Core_Session::setStatus(ts('Unable to remove directory %1', array(1 => $target)), ts('Warning'), 'error');
    }
  }
    }
  }

  /**
   * @param $source
   * @param $destination
   */
  static function copyDir($source, $destination) {
    $dir = opendir($source);
    @mkdir($destination);
    while (FALSE !== ($file = readdir($dir))) {
      if (($file != '.') && ($file != '..')) {
        if (is_dir($source . DIRECTORY_SEPARATOR . $file)) {
          CRM_Utils_File::copyDir($source . DIRECTORY_SEPARATOR . $file, $destination . DIRECTORY_SEPARATOR . $file);
        }
        else {
          copy($source . DIRECTORY_SEPARATOR . $file, $destination . DIRECTORY_SEPARATOR . $file);
        }
      }
    }
    closedir($dir);
  }

  /**
   * Given a file name, recode it (in place!) to UTF-8
   *
   * @param string $name name of file
   *
   * @return boolean  whether the file was recoded properly
   * @access public
   */
  static function toUtf8($name) {
    static $config = NULL;
    static $legacyEncoding = NULL;
    if ($config == NULL) {
      $config = CRM_Core_Config::singleton();
      $legacyEncoding = $config->legacyEncoding;
    }

    if (!function_exists('iconv')) {

      return FALSE;

    }

    $contents = file_get_contents($name);
    if ($contents === FALSE) {
      return FALSE;
    }

    $contents = iconv($legacyEncoding, 'UTF-8', $contents);
    if ($contents === FALSE) {
      return FALSE;
    }

    $file = fopen($name, 'w');
    if ($file === FALSE) {
      return FALSE;
    }

    $written = fwrite($file, $contents);
    $closed = fclose($file);
    if ($written === FALSE or !$closed) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Appends trailing slashed to paths
   *
   * @param $name
   * @param null $separator
   *
   * @return string
   * @access public
   * @static
   */
  static function addTrailingSlash($name, $separator = NULL) {
    if (!$separator) {
      $separator = DIRECTORY_SEPARATOR;
    }

    if (substr($name, -1, 1) != $separator) {
      $name .= $separator;
    }
    return $name;
  }

  /**
   * @param $dsn
   * @param $fileName
   * @param null $prefix
   * @param bool $isQueryString
   * @param bool $dieOnErrors
   */
  static function sourceSQLFile($dsn, $fileName, $prefix = NULL, $isQueryString = FALSE, $dieOnErrors = TRUE) {
    require_once 'DB.php';

    $db = DB::connect($dsn);
    if (PEAR::isError($db)) {
      die("Cannot open $dsn: " . $db->getMessage());
    }
    if (CRM_Utils_Constant::value('CIVICRM_MYSQL_STRICT', CRM_Utils_System::isDevelopment())) {
      $db->query('SET SESSION sql_mode = STRICT_TRANS_TABLES');
    }

    if (!$isQueryString) {
      $string = $prefix . file_get_contents($fileName);
    }
    else {
      // use filename as query string
      $string = $prefix . $fileName;
    }

    //get rid of comments starting with # and --

    $string = preg_replace("/^#[^\n]*$/m", "\n", $string);
    $string = preg_replace("/^(--[^-]).*/m", "\n", $string);

    $queries = preg_split('/;\s*$/m', $string);
    foreach ($queries as $query) {
      $query = trim($query);
      if (!empty($query)) {
        CRM_Core_Error::debug_query($query);
        $res = &$db->query($query);
        if (PEAR::isError($res)) {
          if ($dieOnErrors) {
            die("Cannot execute $query: " . $res->getMessage());
          }
          else {
            echo "Cannot execute $query: " . $res->getMessage() . "<p>";
          }
        }
      }
    }
  }

  /**
   * @param $ext
   *
   * @return bool
   */
  static function isExtensionSafe($ext) {
    static $extensions = NULL;
    if (!$extensions) {
      $extensions = CRM_Core_OptionGroup::values('safe_file_extension', TRUE);

      //make extensions to lowercase
      $extensions = array_change_key_case($extensions, CASE_LOWER);
      // allow html/htm extension ONLY if the user is admin
      // and/or has access CiviMail
      if (!(CRM_Core_Permission::check('access CiviMail') ||
          CRM_Core_Permission::check('administer CiviCRM') ||
          (CRM_Mailing_Info::workflowEnabled() &&
            CRM_Core_Permission::check('create mailings')
          )
        )) {
        unset($extensions['html']);
        unset($extensions['htm']);
      }
    }
    //support lower and uppercase file extensions
    return isset($extensions[strtolower($ext)]) ? TRUE : FALSE;
  }

  /**
   * Determine whether a given file is listed in the PHP include path
   *
   * @param string $name name of file
   *
   * @return boolean  whether the file can be include()d or require()d
   */
  static function isIncludable($name) {
    $x = @fopen($name, 'r', TRUE);
    if ($x) {
      fclose($x);
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * remove the 32 bit md5 we add to the fileName
   * also remove the unknown tag if we added it
   */
  static function cleanFileName($name) {
    // replace the last 33 character before the '.' with null
    $name = preg_replace('/(_[\w]{32})\./', '.', $name);
    return $name;
  }

  /**
   * @param $name
   *
   * @return string
   */
  static function makeFileName($name) {
    $uniqID   = md5(uniqid(rand(), TRUE));
    $info     = pathinfo($name);
    $basename = substr($info['basename'],
      0, -(strlen(CRM_Utils_Array::value('extension', $info)) + (CRM_Utils_Array::value('extension', $info) == '' ? 0 : 1))
    );
    if (!self::isExtensionSafe(CRM_Utils_Array::value('extension', $info))) {
      // munge extension so it cannot have an embbeded dot in it
      // The maximum length of a filename for most filesystems is 255 chars.
      // We'll truncate at 240 to give some room for the extension.
      return CRM_Utils_String::munge("{$basename}_" . CRM_Utils_Array::value('extension', $info) . "_{$uniqID}", '_', 240) . ".unknown";
    }
    else {
      return CRM_Utils_String::munge("{$basename}_{$uniqID}", '_', 240) . "." . CRM_Utils_Array::value('extension', $info);
    }
  }

  /**
   * @param $path
   * @param $ext
   *
   * @return array
   */
  static function getFilesByExtension($path, $ext) {
    $path  = self::addTrailingSlash($path);
    $dh    = opendir($path);
    $files = array();
    while (FALSE !== ($elem = readdir($dh))) {
      if (substr($elem, -(strlen($ext) + 1)) == '.' . $ext) {
        $files[] .= $path . $elem;
      }
    }
    closedir($dh);
    return $files;
  }

  /**
   * Restrict access to a given directory (by planting there a restrictive .htaccess file)
   *
   * @param string $dir the directory to be secured
   * @param bool $overwrite
   */
  static function restrictAccess($dir, $overwrite = FALSE) {
    // note: empty value for $dir can play havoc, since that might result in putting '.htaccess' to root dir
    // of site, causing site to stop functioning.
    // FIXME: we should do more checks here -
    if (!empty($dir) && is_dir($dir)) {
      $htaccess = <<<HTACCESS
<Files "*">
  Order allow,deny
  Deny from all
</Files>

HTACCESS;
      $file = $dir . '.htaccess';
      if ($overwrite || !file_exists($file)) {
        if (file_put_contents($file, $htaccess) === FALSE) {
          CRM_Core_Error::movedSiteError($file);
        }
      }
    }
  }

  /**
   * Restrict remote users from browsing the given directory.
   *
   * @param $publicDir
   */
  static function restrictBrowsing($publicDir) {
    if (!is_dir($publicDir) || !is_writable($publicDir)) {
      return;
    }

    // base dir
    $nobrowse = realpath($publicDir) . '/index.html';
    if (!file_exists($nobrowse)) {
      @file_put_contents($nobrowse, '');
    }

    // child dirs
    $dir = new RecursiveDirectoryIterator($publicDir);
    foreach ($dir as $name => $object) {
      if (is_dir($name) && $name != '..') {
        $nobrowse = realpath($name) . '/index.html';
        if (!file_exists($nobrowse)) {
          @file_put_contents($nobrowse, '');
        }
      }
    }
  }

  /**
   * Create the base file path from which all our internal directories are
   * offset. This is derived from the template compile directory set
   */
  static function baseFilePath($templateCompileDir = NULL) {
    static $_path = NULL;
    if (!$_path) {
      if ($templateCompileDir == NULL) {
        $config = CRM_Core_Config::singleton();
        $templateCompileDir = $config->templateCompileDir;
      }

      $path = dirname($templateCompileDir);

      //this fix is to avoid creation of upload dirs inside templates_c directory
      $checkPath = explode(DIRECTORY_SEPARATOR, $path);

      $cnt = count($checkPath) - 1;
      if ($checkPath[$cnt] == 'templates_c') {
        unset($checkPath[$cnt]);
        $path = implode(DIRECTORY_SEPARATOR, $checkPath);
      }

      $_path = CRM_Utils_File::addTrailingSlash($path);
    }
    return $_path;
  }

  /**
   * @param $directory
   *
   * @return string
   */
  static function relativeDirectory($directory) {
    // Do nothing on windows
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
      return $directory;
    }

    // check if directory is relative, if so return immediately
    if (substr($directory, 0, 1) != DIRECTORY_SEPARATOR) {
      return $directory;
    }

    // make everything relative from the baseFilePath
    $basePath = self::baseFilePath();
    // check if basePath is a substr of $directory, if so
    // return rest of string
    if (substr($directory, 0, strlen($basePath)) == $basePath) {
      return substr($directory, strlen($basePath));
    }

    // return the original value
    return $directory;
  }

  /**
   * @param $directory
   *
   * @return string
   */
  static function absoluteDirectory($directory) {
    // Do nothing on windows - config will need to specify absolute path
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
      return $directory;
    }

    // check if directory is already absolute, if so return immediately
    if (substr($directory, 0, 1) == DIRECTORY_SEPARATOR) {
      return $directory;
    }

    // make everything absolute from the baseFilePath
    $basePath = self::baseFilePath();

    return $basePath . $directory;
  }

  /**
   * Make a file path relative to some base dir
   *
   * @param $directory
   * @param $basePath
   *
   * @return string
   */
  static function relativize($directory, $basePath) {
    if (substr($directory, 0, strlen($basePath)) == $basePath) {
      return substr($directory, strlen($basePath));
    } else {
      return $directory;
    }
  }

  /**
   * Create a path to a temporary file which can endure for multiple requests
   *
   * TODO: Automatic file cleanup using, eg, TTL policy
   *
   * @param $prefix string
   *
   * @return string, path to an openable/writable file
   * @see tempnam
   */
  static function tempnam($prefix = 'tmp-') {
    //$config = CRM_Core_Config::singleton();
    //$nonce = md5(uniqid() . $config->dsn . $config->userFrameworkResourceURL);
    //$fileName = "{$config->configAndLogDir}" . $prefix . $nonce . $suffix;
    $fileName = tempnam(sys_get_temp_dir(), $prefix);
    return $fileName;
  }

  /**
   * Create a path to a temporary directory which can endure for multiple requests
   *
   * TODO: Automatic file cleanup using, eg, TTL policy
   *
   * @param $prefix string
   *
   * @return string, path to an openable/writable directory; ends with '/'
   * @see tempnam
   */
  static function tempdir($prefix = 'tmp-') {
    $fileName = self::tempnam($prefix);
    unlink($fileName);
    mkdir($fileName, 0700);
    return $fileName . '/';
  }

  /**
   * Search directory tree for files which match a glob pattern.
   *
   * Note: Dot-directories (like "..", ".git", or ".svn") will be ignored.
   *
   * @param $dir string, base dir
   * @param $pattern string, glob pattern, eg "*.txt"
   * @return array(string)
   */
  static function findFiles($dir, $pattern) {
    $todos = array($dir);
    $result = array();
    while (!empty($todos)) {
      $subdir = array_shift($todos);
      $matches = glob("$subdir/$pattern");
      if (is_array($matches)) {
        foreach ($matches as $match) {
          if (!is_dir($match)) {
            $result[] = $match;
          }
        }
      }
      $dh = opendir($subdir);
      if ($dh) {
        while (FALSE !== ($entry = readdir($dh))) {
          $path = $subdir . DIRECTORY_SEPARATOR . $entry;
          if ($entry{0} == '.') {
            // ignore
          } elseif (is_dir($path)) {
            $todos[] = $path;
          }
        }
        closedir($dh);
      }
    }
    return $result;
  }

  /**
   * Determine if $child is a sub-directory of $parent
   *
   * @param string $parent
   * @param string $child
   * @param bool $checkRealPath
   *
   * @return bool
   */
  static function isChildPath($parent, $child, $checkRealPath = TRUE) {
    if ($checkRealPath) {
      $parent = realpath($parent);
      $child = realpath($child);
    }
    $parentParts = explode('/', rtrim($parent, '/'));
    $childParts = explode('/', rtrim($child, '/'));
    while (($parentPart = array_shift($parentParts)) !== NULL) {
      $childPart = array_shift($childParts);
      if ($parentPart != $childPart) {
        return FALSE;
      }
    }
    if (empty($childParts)) {
      return FALSE; // same directory
    } else {
      return TRUE;
    }
  }

  /**
   * Move $fromDir to $toDir, replacing/deleting any
   * pre-existing content.
   *
   * @param string $fromDir the directory which should be moved
   * @param string $toDir the new location of the directory
   * @param bool $verbose
   *
   * @return bool TRUE on success
   */
  static function replaceDir($fromDir, $toDir, $verbose = FALSE) {
    if (is_dir($toDir)) {
      if (!self::cleanDir($toDir, TRUE, $verbose)) {
        return FALSE;
      }
    }

    // return rename($fromDir, $toDir); // CRM-11987, https://bugs.php.net/bug.php?id=54097

    CRM_Utils_File::copyDir($fromDir, $toDir);
    if (!CRM_Utils_File::cleanDir($fromDir, TRUE, FALSE)) {
       CRM_Core_Session::setStatus(ts('Failed to clean temp dir: %1', array(1 => $fromDir)), '', 'alert');
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Create a static file (e.g. css or js) in the dynamic resource directory
   * Note: if the file already exists it will be overwritten
   * @param string $fileName
   * @param string $contents
   */
  static function addDynamicResource($fileName, $contents) {
    // First ensure the directory exists
    $path = self::dynamicResourcePath();
    if (!is_dir($path)) {
      self::createDir($path);
      self::restrictBrowsing($path);
    }
    file_put_contents("$path/$fileName", $contents);
  }

  /**
   * Get the path of a dynamic resource file
   * With no fileName supplied, returns the path of the directory
   * @param string $fileName
   * @return string
   */
  static function dynamicResourcePath($fileName = NULL) {
    $config = CRM_Core_Config::singleton();
    // FIXME: Use self::baseFilePath once url issue has been resolved
    $path = self::addTrailingSlash(str_replace(array('/persist/contribute', '\persist\contribute'), '', $config->imageUploadDir)) . 'dynamic';
    if ($fileName !== NULL) {
      $path .= "/$fileName";
    }
    return $path;
  }

  /**
   * Get the URL of a dynamic resource file
   * @param string $fileName
   * @return string
   */
  static function dynamicResourceUrl($fileName) {
    $config = CRM_Core_Config::singleton();
    // FIXME: Need a better way of getting the url of the baseFilePath
    return self::addTrailingSlash(str_replace('/persist/contribute', '', $config->imageUploadURL), '/') . 'dynamic/' . $fileName;
  }

  /**
   * Delete all files from the dynamic resource directory
   */
  static function flushDynamicResources() {
    $files = glob(self::dynamicResourcePath('*'));
    foreach ($files ? $files : array() as $file) {
      if (is_file($file)) {
        unlink($file);
      }
    }
  }
}

