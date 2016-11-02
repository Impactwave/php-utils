<?php

/**
 * Hybrid sprintf.
 * Formats a message with or without HTML formatting, depending on whether the script is running on the CLI or not.
 *
 * @param string $webFormat HTML-formatted text with sprintf-compatible placeholders.
 * @param string $cliFormat unformatted text with sprintf-compatible placeholders.
 * @param mixed  ...$val    Values for each of the placeholders.
 * @return string
 */
function hsprintf ($webFormat, $cliFormat, ...$val)
{
  return sprintf (isCLI () ? $cliFormat : $webFormat, ...$val);
}

/**
 * Returns an Horiontal Rule comprised of the specified character pattern, with the same size as the terminal width.
 *
 * @param string $char
 * @return string
 */
function hr ($char)
{
  return str_repeat ($char, intval (`tput cols`));
}

function json_save ($path, $data, $pretty = true)
{
  $json = json_encode ($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | ($pretty ? JSON_PRETTY_PRINT : 0));
  $json = preg_replace_callback ('/^ +/m', function ($m) {
    return str_repeat (' ', strlen ($m[0]) / 2);
  }, $json);
  file_put_contents ($path, $json);
}

function json_load ($path, $assoc = false)
{
  return json_decode (file_get_contents ($path), $assoc);
}

/**
 * Removes a directory recursively.
 *
 * @param string $dir path.
 */
function rrmdir ($dir)
{
  if (strtoupper (substr (PHP_OS, 0, 3)) === 'WIN') {
    if (is_symlink ($dir)) {
      $target = symlink_target ($dir);
      if (is_dir ($target))
        rmdir ($dir);
      else
        unlink ($dir);
    }
    else if (is_dir ($dir)) {
      foreach (scandir ($dir) as $subdir) {
        if ($subdir == "." || $subdir == "..")
          continue;
        rrmdir ($dir . "/" . $subdir);
      }
      rmdir ($dir);
    }
    else {
      unlink ($dir);
    }
  }
  else {
    if (is_dir ($dir)) {
      $objects = scandir ($dir);
      foreach ($objects as $object) {
        if ($object != "." && $object != "..") {
          if (filetype ($dir . "/" . $object) == "dir")
            rrmdir ($dir . "/" . $object);
          else unlink ($dir . "/" . $object);
        }
      }
      reset ($objects);
      rmdir ($dir);
    }
  }
}

/**
 * returns if target path is of a symlink
 *
 * @param $target
 * @return bool
 */
function is_symlink ($target)
{
  $target   = strtr ($target, '\\', '/');
  $realpath = strtr (realpath ($target), '\\', '/');

  if ($realpath !== $target) // it is a symlink
    return true;

  // fallback for broken symlinks
  $link = strtr (readlink ($target), '\\', '/');

  if ($link && $link !== $target)// it is a broken symlink
    return true;

  return false;
}


/**
 * returns target of a symlink
 *
 * @param $path
 * @return string
 */
function symlink_target ($path)
{
  $path     = strtr ($path, '\\', '/');
  $realpath = strtr (realpath ($path), '\\', '/');

  if ($realpath !== $path) // it is a symlink
    return $realpath;

  // fallback for broken symlinks
  $link = strtr (readlink ($path), '\\', '/');

  if ($link && $link !== $path)// it is a broken symlink
    return $link;

  return $path;
}


define ('DIR_LIST_ALL', 0);
define ('DIR_LIST_FILES', 1);
define ('DIR_LIST_DIRECTORIES', 2);

/**
 * List files and/or directories inside the specified path.
 *
 * Note: the `.` and `..` directories are not returned.
 *
 * @param string   $path
 * @param int      $type      One of the DIR_LIST_XXX constants.
 * @param bool     $fullPaths When `true` returns the full path name of each file, otherwise returns the file name only.
 * @param int|bool $sortOrder Either `false` (no sort), SORT_ASC or SORT_DESC.
 * @return false|string[] `false` if not a valid directory.
 */
function dirList ($path, $type = 0, $fullPaths = false, $sortOrder = false)
{
  if (!file_exists ($path))
    return false;
  $d = new DirectoryIterator($path);
  $o = [];
  foreach ($d as $file) {
    /** @var DirectoryIterator $file */
    if ($file->isDot ()) continue;
    if ($type == 1 && !$file->isFile ())
      continue;
    if ($type == 2 && !$file->isDir ())
      continue;
    $o[] = $fullPaths ? $file->getPathname () : $file->getFilename ();
  }
  if ($sortOrder)
    sort ($o, $sortOrder);
  return $o;
}

/**
 * Returns the target path as relative reference from the base path.
 *
 * Only the URIs path component (no schema, host etc.) is relevant and must be given, starting with a slash.
 * Both paths must be absolute and not contain relative parts.
 * Relative URLs from one resource to another are useful when generating self-contained downloadable document archives.
 * Furthermore, they can be used to reduce the link size in documents.
 *
 * Example target paths, given a base path of "/a/b/c/d":
 * - "/a/b/c/d"     -> ""
 * - "/a/b/c/"      -> "./"
 * - "/a/b/"        -> "../"
 * - "/a/b/c/other" -> "other"
 * - "/a/x/y"       -> "../../x/y"
 *
 * @param string $basePath   The base path
 * @param string $targetPath The target path
 *
 * @return string The relative target path
 *
 * @copyright Fabien Potencier <fabien@symfony.com>
 */
function getRelativePath ($basePath, $targetPath)
{
  if ($basePath === $targetPath) {
    return '';
  }

  $sourceDirs = explode ('/', isset($basePath[0]) && '/' === $basePath[0] ? substr ($basePath, 1) : $basePath);
  $targetDirs = explode ('/', isset($targetPath[0]) && '/' === $targetPath[0] ? substr ($targetPath, 1) : $targetPath);
  array_pop ($sourceDirs);
  $targetFile = array_pop ($targetDirs);

  foreach ($sourceDirs as $i => $dir) {
    if (isset($targetDirs[$i]) && $dir === $targetDirs[$i]) {
      unset($sourceDirs[$i], $targetDirs[$i]);
    }
    else {
      break;
    }
  }

  $targetDirs[] = $targetFile;
  $path         = str_repeat ('../', count ($sourceDirs)) . implode ('/', $targetDirs);

  // A reference to the same base directory or an empty subdirectory must be prefixed with "./".
  // This also applies to a segment with a colon character (e.g., "file:colon") that cannot be used
  // as the first segment of a relative-path reference, as it would be mistaken for a scheme name
  // (see http://tools.ietf.org/html/rfc3986#section-4.2).
  return '' === $path || '/' === $path[0]
         || false !== ($colonPos = strpos ($path, ':')) &&
            ($colonPos < ($slashPos = strpos ($path, '/')) || false === $slashPos)
    ? "./$path" : $path;
}

/**
 * Creates a temporary directory.
 *
 * @param        $dir
 * @param string $prefix
 * @param int    $mode
 *
 * @return string
 */
function tempdir ($dir, $prefix = '', $mode = 0700)
{
  if (substr ($dir, -1) != '/') $dir .= '/';
  do {
    $path = $dir . $prefix . mt_rand (0, 9999999);
  } while (!mkdir ($path, $mode));

  return normalizePath ($path);
}

/**
 * Normalizes a filesystem path, converting Windows directory separators to UNIX-compatible forward slashes.
 *
 * @param string $path
 * @return string
 */
function normalizePath ($path)
{
  return str_replace ('\\', '/', $path);
}

/**
 * Returns an ancestor directory of the given directory, `n` levels above.
 *
 * @param string $path   The starting path.
 * @param int    $levels How many times to travel up the directory hierarchy.
 * @return string The resulting path.
 */
function updir ($path, $levels = 1)
{
  if (PHP_MAJOR_VERSION >= 7)
    return dirname ($path, $levels);
  while ($levels--) $path = dirname ($path);
  return $path;
}

/**
 * Similar to {@see updir}, but it returns an empty string if `$path` is already at the root level.
 *
 * @param string $path   The starting path.
 * @param int    $levels How many times to travel up the directory hierarchy.
 * @return string The resulting path.
 */
function dirnameEx ($path, $levels = 1)
{
  $path = updir ($path, $levels);
  return $path == DIRECTORY_SEPARATOR ? '' : $path;
}

function fileExists ($filename, $useIncludePath = true)
{
  return $useIncludePath ? boolval (stream_resolve_include_path ($filename)) : file_exists ($filename);
}

/**
 * Loads and executes the specified PHP file, searching for it on the 'include path'.
 *
 * @param string $filename
 * @return bool|mixed
 */
function includeFile ($filename)
{
  $path = stream_resolve_include_path ($filename);
  return $path ? require $path : false;
}

/**
 * Loads the specified file, optionally searching the include path, and stripping the Unicode Byte Order Mark (BOM), if
 * one is present.
 *
 * @param string    $filename
 * @param bool|true $useIncludePath
 * @return false|string `false` if the file is not found.
 */
function loadFile ($filename, $useIncludePath = true)
{
  if ($useIncludePath) {
    if (!($filename = stream_resolve_include_path ($filename))) return false;;
  }
  else if (!file_exists ($filename)) return false;
  return removeBOM (file_get_contents ($filename));
}

function removeBOM ($string)
{
  if (substr ($string, 0, 3) == pack ('CCC', 0xef, 0xbb, 0xbf))
    $string = substr ($string, 3);
  return $string;
}
