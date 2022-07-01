<?php

declare(strict_types=1);

namespace Xylemical\Composer\ClassDiscovery;

use Xylemical\Discovery\ClassSourceInterface;
use Xylemical\Discovery\Source\SourceDependents;
use Xylemical\Discovery\Source\SourceExpansion;
use Xylemical\Discovery\SourceFactory;
use Xylemical\Discovery\SourceFactoryInterface;
use function explode;
use function file_put_contents;
use function preg_replace;
use function trim;

/**
 * Provides the storage for source dependencies.
 */
class ClassDiscoveryStorage {

  /**
   * The source path.
   *
   * @var string
   */
  protected string $path;

  /**
   * The source factory used for the discovery.
   *
   * @var \Xylemical\Discovery\SourceFactoryInterface
   */
  protected SourceFactoryInterface $factory;

  /**
   * SourceDependentsStorage constructor.
   *
   * @param string $path
   *   The source path, leave empty for default location.
   */
  public function __construct(string $path = '') {
    $this->path = $path ?: realpath(__DIR__ . '/../source');
  }

  /**
   * Clear the storage.
   *
   * @return $this
   */
  public function clear(): static {
    $iterator = new \RecursiveDirectoryIterator($this->path, \FilesystemIterator::SKIP_DOTS);
    $iterator = new \RecursiveIteratorIterator($iterator, \RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $path) {
      if ($path->isDir()) {
        $path->isLink() ? @unlink($path->getPathname()) : @rmdir($path->getPathname());
      }
      elseif (preg_match('/\.php$/', $path->getFilename())) {
        @unlink($path->getPathname());
      }
    }
    return $this;
  }

  /**
   * Get all dependents for a source.
   *
   * @param string $source
   *   The source.
   *
   * @return \Xylemical\Discovery\SourceInterface[]
   *   The sources.
   */
  public function get(string $source): array {
    if (!($path = $this->getPath($source)) || !file_exists($path)) {
      return [];
    }
    return $this->getDependencies(include $path);
  }

  /**
   * Set the sources for dependencies.
   *
   * @param \Xylemical\Discovery\SourceInterface[] $sources
   *   The sources.
   *
   * @return $this
   */
  public function set(array $sources): static {
    $sources = (new SourceExpansion())->expand($sources);
    $dependents = (new SourceDependents())->generate($sources);
    foreach ($dependents as $source => $dependencies) {
      $this->write($source, $dependencies);
    }
    return $this;
  }

  /**
   * Get the source factory used for the storage.
   *
   * @return \Xylemical\Discovery\SourceFactoryInterface
   *   The source factory.
   */
  public function getSourceFactory(): SourceFactoryInterface {
    if (!isset($this->factory)) {
      $this->factory = new SourceFactory();
    }
    return $this->factory;
  }

  /**
   * Set the source factory for the storage.
   *
   * @param \Xylemical\Discovery\SourceFactoryInterface $factory
   *   The source factory.
   *
   * @return $this
   */
  public function setSourceFactory(SourceFactoryInterface $factory): static {
    $this->factory = $factory;
    return $this;
  }

  /**
   * Write out a dependent file.
   *
   * @param string $source
   *   The source class/interface/trait.
   * @param \Xylemical\Discovery\SourceInterface[] $dependencies
   *   The dependencies of the source.
   *
   * @return $this
   */
  protected function write(string $source, array $dependencies): static {
    if (!($path = $this->getPath($source))) {
      return $this;
    }
    $output = '<?' . "php\n";
    $output .= 'return ' . var_export($this->setDependencies($dependencies), TRUE);
    $output .= ";\n";
    file_put_contents($path, $output);

    return $this;
  }

  /**
   * Convert dependencies into storable array.
   *
   * @param \Xylemical\Discovery\SourceInterface[] $dependencies
   *   The dependencies.
   *
   * @return array
   *   The array.
   */
  protected function setDependencies(array $dependencies): array {
    $result = [];
    foreach ($dependencies as $key => $source) {
      $result[$key] = [
        'type' => $source->getType(),
        'name' => $source->getName(),
        'classes' => $source->getClasses(),
        'interfaces' => $source->getInterfaces(),
        'traits' => $source->getTraits(),
      ];
      if ($source instanceof ClassSourceInterface) {
        $result[$key]['abstract'] = $source->isAbstract();
      }
    }
    return $result;
  }

  /**
   * Convert the dependencies from storable array.
   *
   * @param array $dependencies
   *   The array.
   *
   * @return array
   *   The sources.
   */
  protected function getDependencies(array $dependencies): array {
    $factory = $this->getSourceFactory();

    $result = [];
    foreach ($dependencies as $key => $value) {
      $object = $factory->create($value['type'], $value['name']);
      $object->setClasses($value['classes'])
        ->setInterfaces($value['interfaces'])
        ->setTraits($value['traits']);
      if (isset($value['abstract']) && $object instanceof ClassSourceInterface) {
        $object->setAbstract($value['abstract']);
      }

      $result[$key] = $object;
    }
    return $result;
  }

  /**
   * Get the source path.
   *
   * @param string $source
   *   The source.
   *
   * @return string
   *   The path.
   */
  protected function getPath(string $source): string {
    $parts = explode('\\', trim($source, '\\'));
    $class = $this->normalize(array_pop($parts));
    $target = $this->path;
    foreach ($parts as $namespace) {
      if (!($namespace = $this->normalize($namespace))) {
        return '';
      }

      $path = "{$target}/{$namespace}";
      if (!file_exists($path)) {
        if (!@mkdir($path, 0755)) {
          return '';
        }
      }
      elseif (!is_dir($path)) {
        return '';
      }

      $target .= '/' . $namespace;
    }
    return "{$target}/{$class}.php";
  }

  /**
   * Normalizes the name.
   *
   * @param string $name
   *   The name.
   *
   * @return string
   *   The normalized name.
   */
  protected function normalize(string $name): string {
    return preg_replace('/[^\w\d_]+/', '', $name);
  }

}
