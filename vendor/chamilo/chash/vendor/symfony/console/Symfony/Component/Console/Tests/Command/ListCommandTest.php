<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Command;

use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Application;

class ListCommandTest extends \PHPUnit_Framework_TestCase
{
    public function testExecuteListsCommands()
    {
        $application = new Application();
        $commandTester = new CommandTester($command = $application->get('list'));
        $commandTester->execute(array('command' => $command->getName()), array('decorated' => false));

        $this->assertRegExp('/help   Displays help for a command/', $commandTester->getDisplay(), '->execute() returns a list of available commands');
    }

    public function testExecuteListsCommandsWithXmlOption()
    {
        $application = new Application();
        $commandTester = new CommandTester($command = $application->get('list'));
        $commandTester->execute(array('command' => $command->getName(), '--xml' => true));

        $this->assertRegExp('/<command id="list" name="list">/', $commandTester->getDisplay(), '->execute() returns a list of available commands in XML if --xml is passed');
    }

    public function testExecuteListsCommandsWithRawOption()
    {
        $application = new Application();
        $commandTester = new CommandTester($command = $application->get('list'));
        $commandTester->execute(array('command' => $command->getName(), '--raw' => true));
        $output = <<<EOF
help   Displays help for a command
list   Lists commands

EOF;

        $this->assertEquals(str_replace("\n", PHP_EOL, $output), $commandTester->getDisplay());
    }
}
