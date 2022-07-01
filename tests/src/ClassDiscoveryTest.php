<?php

declare(strict_types=1);

namespace Xylemical\Composer\ClassDiscovery;

use Composer\Composer;
use Composer\IO\IOInterface;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\TestCase;
use Xylemical\Composer\Discovery\ComposerPackage;
use Xylemical\Composer\Discovery\ComposerProject;
use function file_exists;
use function json_encode;

/**
 * Tests \Xylemical\Composer\ClassDiscovery\ClassDiscovery.
 */
class ClassDiscoveryTest extends TestCase {

  /**
   * The root filesystem.
   *
   * @var \org\bovigo\vfs\vfsStreamDirectory
   */
  protected vfsStreamDirectory $root;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->root = vfsStream::setup('root', NULL, $this->setupStructure());
  }

  /**
   * Tests sanity.
   */
  public function testSanity(): void {
    $path = $this->root->url();

    $composer = $this->getMockBuilder(Composer::class)
      ->disableOriginalConstructor()
      ->getMock();
    $io = $this->getMockBuilder(IOInterface::class)->getMock();
    $project = $this->getMockBuilder(ComposerProject::class)
      ->disableOriginalConstructor()
      ->getMock();
    $discovery = new ClassDiscovery($composer, $io, $project, "{$path}/source");

    $this->assertEquals('Class discovery', $discovery->getName());

    foreach (['', '/a', '/b', '/c'] as $subpath) {
      $package = new ComposerPackage('foo/bar', "{$path}{$subpath}");
      $discovery->discover($package);
    }
    $discovery->complete();

    $this->assertFalse(file_exists("{$path}/source/Prefix/For/A/A/A.php"));
    $this->assertFalse(file_exists("{$path}/source/Prefix/For/A/A/B.php"));
    $this->assertFalse(file_exists("{$path}/source/A/A/A.php"));
    $this->assertFalse(file_exists("{$path}/source/A/A/B.php"));
    $this->assertTrue(file_exists("{$path}/source/Prefix/For/A/B/A.php"));
    $this->assertTrue(file_exists("{$path}/source/A/B/A.php"));
  }

  /**
   * Create a structure to be used for testing purposes.
   *
   * @return array
   *   The file structure.
   */
  protected function setupStructure(): array {
    return [
      'source' => [],
      'a' => [
        'composer.json' => $this->setupComposer('psr-4', ['Prefix\\For\\' => 'src']),
        'src' => [
          'A' => [
            'A' => [
              'A.php' => $this->setupCode('abstract class', 'Prefix\\For\\A\\A', 'A implements \\Prefix\\For\\A\\B\\A'),
              'B.php' => $this->setupCode('class', 'Prefix\\For\\A\\A', 'C'),
            ],
            'B' => [
              'A.php' => $this->setupCode('interface', 'Prefix\\For\\A\\B', 'A'),
            ],
          ],
        ],
      ],
      'b' => [
        'composer.json' => $this->setupComposer('psr-0', ['A' => ['lib']]),
        'lib' => [
          'A' => [
            'A' => [
              'A.php' => $this->setupCode('abstract class', 'A\\A', 'A implements \\A\\B\\A'),
              'B.php' => $this->setupCode('class', 'A\\A', 'C'),
            ],
            'B' => [
              'A.php' => $this->setupCode('interface', 'A\\B', 'A'),
            ],
          ],
        ],
      ],
      'c' => [
        'composer.json' => '{}',
      ],
    ];
  }

  /**
   * Creates a composer.json for a package.
   *
   * @param string $type
   *   The autoload type.
   * @param array $src
   *   The autoload contents.
   *
   * @return string
   *   The composer contents.
   */
  protected function setupComposer(string $type, array $src): string {
    $type = json_encode($type);
    $src = json_encode($src);
    return "{ \"autoload\": { $type: $src } }";
  }

  /**
   * Get the PHP file contents for an object.
   *
   * @param string $type
   *   The object type.
   * @param string $namespace
   *   The namespace.
   * @param string $name
   *   The object name.
   *
   * @return string
   *   The file contents.
   */
  protected function setupCode(string $type, string $namespace, string $name): string {
    return '<?' . "php\nnamespace {$namespace};\n{$type} {$name} {  }\n";
  }

}
