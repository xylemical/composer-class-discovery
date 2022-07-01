<?php

declare(strict_types=1);

namespace Xylemical\Composer\ClassDiscovery;

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\TestCase;
use Xylemical\Discovery\ClassSource;
use Xylemical\Discovery\InterfaceSource;
use Xylemical\Discovery\SourceFactory;
use function file_exists;

/**
 * Tests \Xylemical\Composer\ClassDiscovery\ClassDiscoveryStorage.
 */
class ClassDiscoveryStorageTest extends TestCase {

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
    $this->root = vfsStream::setup('root', NULL, [
      'B' => 'Source File',
    ]);
  }

  /**
   * Tests sanity.
   */
  public function testSanity(): void {
    $path = $this->root->url();
    $storage = new ClassDiscoveryStorage($path);
    $a = (new ClassSource('A\\A\\A'))
      ->setAbstract(TRUE)
      ->setInterfaces(['A\\A\\I']);
    $b = (new InterfaceSource('A\\A\\I'));
    $storage->set(['A\\A\\A' => $a, 'A\\A\\I' => $b]);
    $this->assertTrue(file_exists("{$path}/A/A/I.php"));
    $this->assertFalse(file_exists("{$path}/A/A/A.php"));

    $this->assertEquals(['A\\A\\A' => $a], $storage->get('A\\A\\I'));
    $this->assertEquals([], $storage->get('A\\A\\A'));

    $storage->clear();
    $this->assertFalse(file_exists("{$path}/A"));

    $factory = new SourceFactory();
    $this->assertNotSame($factory, $storage->getSourceFactory());
    $storage->setSourceFactory($factory);
    $this->assertSame($factory, $storage->getSourceFactory());

    $a = (new ClassSource('B\\B'))->setInterfaces(['B\\I']);
    $b = (new InterfaceSource('B\\I'));
    $storage->set(['B\\B' => $a, 'B\\I' => $b]);
    $this->assertFalse(file_exists("{$path}/B/I.php"));

    $a = (new ClassSource('%\\B'))->setInterfaces(['%\\I']);
    $b = (new InterfaceSource('%\\I'));
    $storage->set(['%\\B' => $a, '%\\I' => $b]);
    $this->assertFalse(file_exists("{$path}/%/I.php"));

  }

}
