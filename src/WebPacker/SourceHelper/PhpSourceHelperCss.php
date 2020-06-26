<?php
declare(strict_types=1);

use SetBased\Exception\FallenException;
use Webmozart\PathUtil\Path;

/**
 * Interface for source handlers.
 */
class PhpSourceHelperCss
{
  //--------------------------------------------------------------------------------------------------------------------
  use WebPackerTrait;

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * All methods for including CSS sources.
   *
   * @var array
   */
  public static $methods = ['cssAppendSource',
                            'cssAppendSourcesList',
                            'cssOptimizedAppendSource',
                            'cssPushSource',
                            'cssPushSourcesList',
                            'cssOptimizedPushSource'];

  /**
   * The extension of the resource files.
   *
   * @var string
   */
  public $extension = '.css';

  /**
   * The path of the resource dir (relative to the parent resource dir).
   *
   * @var string
   */
  public $resourceDir = 'css';

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * PhpSourceHelperCss constructor.
   *
   * @param WebPackerInterface $parent The parent object.
   */
  public function __construct(WebPackerInterface $parent)
  {
    $this->initWebPackerTrait($parent);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Analyzes a PHP source file for references to resources files. Found references are stored in the store.
   *
   * @param array  $source        The details of the PHP source file.
   * @param array  $lines         The source as lines.
   * @param string $qualifiedName The fully qualified class name of the class found int he PHP source file.
   * @param array  $imports       The imports (use) found in the PHP source file.
   * @param string $namespace     The namespace used in the PHP source file.
   */
  public function analyzePhpSourceFileHelper1(array $source,
                                              array $lines,
                                              string $qualifiedName,
                                              array $imports,
                                              string $namespace): void
  {


    $indent     = '(?<indent>.*)';
    $call       = '(?<call>((Nub::\$)|(\$this->))nub->assets->)';
    $method     = '(?<method>cssAppendSource|cssAppendSourcesList|cssPushSource|cssPushSourcesList)';
    $class      = '(\(\s*)(?<class>__CLASS__|__TRAIT__)';
    $path       = '(\((\s*[\'"])(?<path>[a-zA-Z0-9_\-.\/]+))([\'"])';
    $resolution = '(\(\s*)(?<resolution>[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)::class';
    $other      = '(?<other>(.*))';
    $regex      = '/^'.$indent.$call.$method.'('.$class.'|'.$path.'|'.$resolution.')'.$other.'$/';

    foreach ($lines as $i => $line)
    {
      if (preg_match($regex, $line, $matches, PREG_UNMATCHED_AS_NULL))
      {
        $this->analyzePhpSourceFileHelper2($matches, $qualifiedName, $imports, $namespace, $source, $i + 1);
      }
      else
      {
        // Test for invalid usage of methods for including CSS files.
        foreach (self::$methods as $method)
        {
          if (preg_match("/(->|::)($method)(\\()/", $line))
          {
            $this->task->logError("Unexpected usage of method '%s' at line %s:%d", $method, $source['src_path'], $i + 1);
          }
        }
      }
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Analyzes a line in a PHP source file that calls a WebAssets method.
   *
   * @param array  $matches       The matches as returned by preg_match().
   * @param string $qualifiedName The fully qualified class name of the class found int he PHP source file.
   * @param array  $imports       The imports (use) found in the PHP source file.
   * @param string $namespace     The namespace used in the PHP source file.
   * @param array  $source        The details of the PHP source file.
   * @param int    $lineno        The line number in the PHP source file were the WebAssets method is called.
   */
  private function analyzePhpSourceFileHelper2(array $matches,
                                               string $qualifiedName,
                                               array $imports,
                                               string $namespace,
                                               array $source,
                                               int $lineno): void
  {
    [$path, $expression] = $this->deriveResourcePath($matches, $qualifiedName, $imports, $namespace);

    $this->task->logVerbose('    found %s (%s:%d)', Path::makeRelative($path, $this->buildPath), $expression, $lineno);

    $resource = $this->store->resourceSearchByPath($path);
    if ($resource===null)
    {
      $this->task->logError("Unable to find resource '%s' found at %s:%d", $path, $source['src_path'], $lineno);

      return;
    }

    $this->store->insertRow('ABC_LINK1', ['src_id'      => $source['src_id'],
                                          'rsr_id'      => $resource['rsr_id'],
                                          'lk1_line'    => $lineno,
                                          'lk1_method'  => $matches['method'],
                                          'lk1_matches' => serialize($matches)]);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Derives the full path of the included resource and the expression the refer to the resource.
   *
   * @param array  $matches       The matches from the regexp.
   * @param string $qualifiedName The fully qualified name of the class in the source file.
   * @param array  $imports       The imports in the source file.
   * @param string $namespace     The namespace of the sources file.
   *
   * @return array
   */
  private function deriveResourcePath(array $matches,
                                      string $qualifiedName,
                                      array $imports,
                                      string $namespace): array
  {
    switch ($matches['method'])
    {
      case 'cssAppendSource':
      case 'cssPushSource':
        $extension = $this->extension;
        break;

      case 'cssAppendSourcesList':
      case    'cssPushSourcesList':
        $extension = '.txt';
        break;

      default:
        throw new FallenException('method', $matches['method']);
    }

    switch (true)
    {
      case $matches['class']!==null:
        $filename   = str_replace('\\', '/', $qualifiedName).$extension;
        $expression = $matches['class'];
        break;

      case $matches['path']!==null:
        $filename   = $matches['path'];
        $expression = $matches['path'];
        break;

      case $matches['resolution']!==null:
        if (isset($imports[$matches['resolution']]))
        {
          $tmp = $imports[$matches['resolution']];
        }
        else
        {
          $tmp = $namespace.'\\'.$matches['resolution'];
        }
        $filename   = str_replace('\\', '/', $tmp).$extension;
        $expression = $matches['resolution'].'::class';
        break;

      default:
        throw new LogicException('Regex not correct');
    }

    $path = $this->resolveFullPathOfResource($filename);

    return [$path, $expression];
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Resolves a name of a resource to to full path of the resource on the file system.
   *
   * @param string $resourceName The name of the resource as found in the source file.
   *
   * @return string
   */
  private function resolveFullPathOfResource(string $resourceName): string
  {
    if (substr($resourceName, 0, 1)==='/')
    {
      $fullPath = Path::join([$this->parentResourcePath, $resourceName]);
    }
    else
    {
      $fullPath = Path::join([$this->parentResourcePath, $this->resourceDir, $resourceName]);
    }

    return $fullPath;
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------