<?php

namespace Realodix\Haiku\Test\Feature;

use Realodix\Haiku\Console\OutputLogger;
use Realodix\Haiku\Console\Statistics;
use Realodix\Haiku\Test\TestCase;
use Symfony\Component\Console\Output\OutputInterface;

class OutputLoggerTest extends TestCase
{
    public function testProcessedWritesSimpleMessageOnFullProcessing()
    {
        $testPath = 'path/to/another-file.txt';
        $absolutePath = base_path($testPath);

        $outputMock = \Mockery::mock(OutputInterface::class);
        $outputMock->shouldReceive('write');
        $outputMock->expects('writeln')
            ->with("<info>[P]: {$testPath}</info>");

        $statsMock = \Mockery::mock(Statistics::class);
        $statsMock->expects('incrementProcessed');

        $logger = new OutputLogger($outputMock, $statsMock);
        $logger->processed($absolutePath);
    }

    public function testSkippedWritesToOutputInVerboseMode()
    {
        $testPath = 'path/to/file.txt';
        $absolutePath = base_path($testPath);

        $outputMock = \Mockery::mock(OutputInterface::class);
        // Set isVerbose() to return true
        $outputMock->shouldReceive('isVerbose')->andReturn(true);
        // Set expectations: writeln() should be called 1x with a valid string
        $outputMock->expects('writeln')
            ->with("<fg=gray>[S]: {$testPath}</>");

        $statsMock = \Mockery::mock(Statistics::class);
        $statsMock->expects('incrementSkipped');

        $logger = new OutputLogger($outputMock, $statsMock);
        $logger->skipped($absolutePath);
    }

    public function testSkippedDoesNotWriteToOutputInNormalMode()
    {
        $outputMock = \Mockery::mock(OutputInterface::class);
        // Set isVerbose() to return false
        $outputMock->shouldReceive('isVerbose')->andReturn(false);
        // Set expectations: writeln() should not be called at all
        $outputMock->expects('writeln')
            ->never();

        $statsMock = \Mockery::mock(Statistics::class);
        $statsMock->expects('incrementSkipped');

        $logger = new OutputLogger($outputMock, $statsMock);
        $logger->skipped('path/to/file.txt');
    }

    public function testErrorWritesToOutput()
    {
        $errorMessage = 'Something went wrong.';

        $outputMock = \Mockery::mock(OutputInterface::class);
        $outputMock->expects('writeln')
            ->with("<fg=red>[E]: {$errorMessage}</>");

        $statsMock = \Mockery::mock(Statistics::class);
        $statsMock->expects('incrementError');

        $logger = new OutputLogger($outputMock, $statsMock);
        $logger->error($errorMessage);
    }
}
