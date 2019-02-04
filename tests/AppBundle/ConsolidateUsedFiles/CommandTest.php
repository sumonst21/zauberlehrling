<?php

namespace AppBundle\ConsolidateUsedFiles;

use Helper\FileSystem;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Tests for the ConsolidateUsedFiles console command.
 */
final class CommandTest extends KernelTestCase
{
    /** @var Command */
    private $command;

    /** @var CommandTester */
    private $commandTester;

    /** @var string */
    private $pathToFixture;

    protected function setUp()
    {
        // set up command tester
        self::bootKernel();
        $application = new Application(self::$kernel);
        $application->add(new Command(new Task()));
        $this->command = $application->find('consolidate-used-files');
        $this->commandTester = new CommandTester($this->command);

        $this->pathToFixture = __DIR__ . '/fixtures/tmp-file-for-testing.txt';
        copy(__DIR__ . '/fixtures/template-to-copy.txt', $this->pathToFixture);
    }

    protected function tearDown()
    {
        unlink($this->pathToFixture);
        parent::tearDown();
    }

    /**
     * @test
     */
    public function successOutput()
    {
        $this->commandTester->execute([
            'command'  => $this->command->getName(),
            'usedFiles' => $this->pathToFixture,
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertContains('[OK]', $output);
    }

    /**
     * @test
     */
    public function fileGetsConsolidated()
    {
        $this->commandTester->execute([
            'command'  => $this->command->getName(),
            'usedFiles' => $this->pathToFixture,
        ]);

        $result = FileSystem::readFileIntoArray($this->pathToFixture);
        $this->assertEquals(['a', 'b', 'c', 'e', 'g'], $result);
    }
}
