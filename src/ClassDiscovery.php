<?php

declare(strict_types=1);

namespace Xylemical\Composer\ClassDiscovery;

use Composer\Composer;
use Composer\IO\IOInterface;
use Xylemical\Composer\Discovery\ComposerDiscoveryBase;
use Xylemical\Composer\Discovery\ComposerPackage;
use Xylemical\Composer\Discovery\ComposerProject;
use Xylemical\Discovery\Directory\NamespaceDiscovery;
use Xylemical\Discovery\SourceFactory;
use function trim;

/**
 * Provides class discovery for each package.
 */
class ClassDiscovery extends ComposerDiscoveryBase {

  /**
   * A list of all sources.
   *
   * @var \Xylemical\Discovery\SourceInterface[]
   */
  protected array $sources = [];

  /**
   * The path.
   *
   * @var string
   */
  protected string $path;

  /**
   * {@inheritdoc}
   */
  public function __construct(Composer $composer, IOInterface $io, ComposerProject $project, string $path = '') {
    parent::__construct($composer, $io, $project);
    $this->path = $path;
  }

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return 'Class discovery';
  }

  /**
   * {@inheritdoc}
   */
  public function discover(ComposerPackage $package): void {
    $root = $package->getPath();
    if (!file_exists("{$root}/composer.json")) {
      return;
    }

    $contents = file_get_contents("{$root}/composer.json");
    $contents = json_decode($contents, TRUE);
    if (!isset($contents['autoload'])) {
      return;
    }

    $factory = new SourceFactory();
    foreach (['psr-0', 'psr-4'] as $type) {
      if (!isset($contents['autoload'][$type])) {
        continue;
      }

      foreach ($contents['autoload'][$type] as $namespace => $directory) {
        foreach ((array) $directory as $dir) {
          $path = "{$root}/{$dir}";
          if (file_exists($path)) {
            $namespace = $type === 'psr-4' ? trim($namespace, '\\') : '';
            $discovery = new NamespaceDiscovery($factory, $namespace, $path);
            $this->sources += $discovery->discover();
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function complete(): void {
    $storage = new ClassDiscoveryStorage($this->path);
    $storage->clear()->set($this->sources);
  }

}
