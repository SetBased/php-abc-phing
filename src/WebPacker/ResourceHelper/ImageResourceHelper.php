<?php
declare(strict_types=1);

namespace Plaisio\Phing\Task\WebPacker\ResourceHelper;

use Plaisio\Phing\Task\WebPacker\WebPackerInterface;
use Plaisio\Phing\Task\WebPacker\WebPackerTrait;
use Webmozart\PathUtil\Path;

/**
 * Helper class for image resources.
 */
class ImageResourceHelper implements ResourceHelper, WebPackerInterface
{
  //--------------------------------------------------------------------------------------------------------------------
  use WebPackerTrait;

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Object constructor.
   *
   * @param WebPackerInterface $parent The parent object.
   */
  public function __construct(WebPackerInterface $parent)
  {
    $this->initWebPackerTrait($parent);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * @inheritDoc
   */
  public static function deriveType(string $content): bool
  {
    unset($content);

    return true;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * @inheritDoc
   */
  public static function mustCompress(): bool
  {
    return false;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * @inheritDoc
   */
  public function analyze(array $resource): void
  {
    unset($resource);
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * @inheritDoc
   */
  public function optimize(array $resource, array $resources): string
  {
    unset($resources);

    // Nothing to do for images.
    return $resource['rsr_content'];
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * @inheritDoc
   */
  public function uriOptimizedPath(array $resource): ?string
  {
    $md5       = md5($resource['rsr_content_optimized'] ?? '');
    $extension = Path::getExtension($resource['rsr_path']);

    return sprintf('/%s/%s.%s', $this->imageDir, $md5, $extension);
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
