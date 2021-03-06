<?php

declare(strict_types=1);

namespace DepDocTest\Command;

use DepDoc\Command\UpdateCommand;
use DepDoc\Configuration\ConfigurationService;
use DepDoc\PackageManager\ComposerPackageManager;
use DepDoc\PackageManager\NodePackageManager;
use DepDoc\PackageManager\Package\PackageManagerPackageInterface;
use DepDoc\PackageManager\PackageList\PackageManagerPackageList;
use DepDoc\Parser\ParserInterface;
use DepDoc\Writer\WriterConfiguration;
use DepDoc\Writer\WriterInterface;
use phpmock\MockRegistry;
use phpmock\prophecy\PHPProphet;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateCommandTest extends TestCase
{
    /** @var PHPProphet */
    protected $prophet;

    protected function setUp()
    {
        $this->prophet = new PHPProphet();
    }

    protected function tearDown()
    {
        MockRegistry::getInstance()->unregisterAll();
    }

    public function testItHasSetDescription(): void
    {
        $command = new UpdateCommand(
            $this->prophesize(WriterInterface::class)->reveal(),
            $this->prophesize(ParserInterface::class)->reveal(),
            $this->prophesize(ComposerPackageManager::class)->reveal(),
            $this->prophesize(NodePackageManager::class)->reveal(),
            $this->prophesize(ConfigurationService::class)->reveal()
        );

        $this->assertEquals(
            'Update or create a DEPENDENCIES.md',
            $command->getDescription(),
            'description should be as expected'
        );
    }

    public function testItExitsEarlyIfParentHasAnExitCodeNotEqualToZero(): void
    {
        $composerPackageManager = $this->prophesize(
            ComposerPackageManager::class
        );
        $composerPackageManager->getInstalledPackages(
            Argument::type('string')
        )->shouldNotBeCalled();

        $command = new UpdateCommand(
            $this->prophesize(WriterInterface::class)->reveal(),
            $this->prophesize(ParserInterface::class)->reveal(),
            $composerPackageManager->reveal(),
            $this->prophesize(NodePackageManager::class)->reveal(),
            $this->prophesize(ConfigurationService::class)->reveal()
        );

        $input = $this->getDefaultInputProphecy();
        $output = $this->getDefaultOutputProphecy();

        $input->getOption('directory')->willReturn('')->shouldBeCalledOnce();

        $this->assertEquals(
            -1,
            $command->run($input->reveal(), $output->reveal())
        );
    }

    public function testItWritesDependenciesFile(): void
    {
        $packageManagerPackage = $this->prophesize(
            PackageManagerPackageInterface::class
        );
        $packageManagerPackage->getManagerName()->willReturn('composer');
        $packageManagerPackage->getName()->willReturn('test');

        $packages = $this->prophesize(PackageManagerPackageList::class);
        $packages
            ->getAllFlat()
            ->willReturn([ $packageManagerPackage->reveal() ])
            ->shouldBeCalled();

        $composerPackageManager = $this->prophesize(
            ComposerPackageManager::class
        );
        $composerPackageManager
            ->getInstalledPackages(Argument::type('string'))
            ->willReturn($packages->reveal())
            ->shouldBeCalled();

        $nodePackageManager = $this->prophesize(NodePackageManager::class);
        $nodePackageManager
            ->getInstalledPackages(Argument::type('string'))
            ->willReturn($packages->reveal())
            ->shouldBeCalled();

        $parser = $this->prophesize(ParserInterface::class);
        $parser->getDocumentedDependencies(
            Argument::type('string')
        )->willReturn(
            $this->prophesize(PackageManagerPackageList::class)->reveal()
        );

        $writerConfiguration = $this->prophesize(WriterConfiguration::class);
        $writerConfiguration->setNewline(Argument::type('string'));

        $writer = $this->prophesize(WriterInterface::class);
        $writer->getConfiguration()->willReturn($writerConfiguration->reveal());
        $writer->createDocumentation(
            Argument::type('string'),
            Argument::type(PackageManagerPackageList::class),
            Argument::type(PackageManagerPackageList::class)
        );

        $command = new UpdateCommand(
            $writer->reveal(),
            $parser->reveal(),
            $composerPackageManager->reveal(),
            $nodePackageManager->reveal(),
            $this->prophesize(ConfigurationService::class)->reveal()
        );

        $input = $this->getDefaultInputProphecy();
        $output = $this->getDefaultOutputProphecy();

        $input->getOption('directory')->willReturn('/test');

        $prophecy = $this->prophet->prophesize('DepDoc\\Command');
        $prophecy
            ->file_exists(Argument::type('string'))
            ->shouldBeCalledTimes(1)
            ->willReturn(true);
        $prophecy
            ->realpath(Argument::type('string'))
            ->shouldBeCalledTimes(1)
            ->willReturn(true);

        $prophecy->reveal();

        $this->assertEquals(
            0,
            $command->run($input->reveal(), $output->reveal())
        );

        $this->prophet->checkPredictions();
    }

    /**
     * @return ObjectProphecy|InputInterface
     */
    protected function getDefaultInputProphecy()
    {
        $input = $this->prophesize(InputInterface::class);

        $input->bind(Argument::any())->willReturn(null);
        $input->isInteractive()->willReturn(false);
        $input->hasArgument('command')->willReturn(false);
        $input->validate()->willReturn(null);

        return $input;
    }

    /**
     * @return ObjectProphecy|OutputInterface
     */
    protected function getDefaultOutputProphecy()
    {
        $output = $this->prophesize(OutputInterface::class);

        $output->getFormatter()->willReturn(
            $this->prophesize(OutputFormatterInterface::class)->reveal()
        );
        $output->getVerbosity()->willReturn(0);
        $output->write(Argument::cetera())->willReturn(null);
        $output->writeln(Argument::cetera())->willReturn(null);
        $output->isDecorated()->willReturn(false);
        $output->isVerbose()->willReturn(false);
        $output->isVeryVerbose()->willReturn(false);

        return $output;
    }
}
