<?php
/*
 *   Dominate: Advanced command library for PocketMine-MP
 *   Copyright (C) 2016  Chris Prime
 *
 *   This program is free software: you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation, either version 3 of the License, or
 *   (at your option) any later version.
 *
 *   This program is distributed in the hope that it will be useful,
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   GNU General Public License for more details.
 *
 *   You should have received a copy of the GNU General Public License
 *   along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
namespace dominate;

use pocketmine\command\Command as PocketMineCommand;
use pocketmine\command\PluginIdentifiableCommand;
use pocketmine\command\CommandSender;
use pocketmine\plugin\Plugin;

use dominate\argument\Argument;
use dominate\requirement\Requirement;

class Command extends PocketMineCommand implements PluginIdentifiableCommand {

	/**
	 * @var Command
	 */
	protected $parent;

	/**
	 * @var Requirement[]
	 */
	protected $requirements = [];

	/**
	 * @var Command[]
	 */
	protected $childs = [];

	/**
	 * @var Argument[]
	 */
	protected $arguments = [];

	/**
	 * Values from each argument
	 * @var mixed[]
	 */
	protected $values = [];

	/**
	 * @var Command
	 */
	protected $endPoint = null;

	// Last execution parameters
	
	/** @var CommandSender */
	protected $sender;

	/** @var string[] */
	protected $args = [];

	/** @var string */
	protected $label;

	/** @var Plugin */
	protected $plugin;

	/**
	 * @param string $name
	 * @param string $description = ""
	 * @param string[] $aliases = []
	 * @param Argument[] $arguments = []
	 * @param Command[] $childs = []
	 */
	public function __construct(Plugin $plugin, string $name, string $description = "", array $aliases = [], array $arguments = [], array $childs = []){
		parent::__construct($name, $description, $aliases);
		$this->plugin = $plugin;
	}

	/*
	 * ----------------------------------------------------------
	 * CHILD (Sub Command)
	 * ----------------------------------------------------------
	 */

	/**
     * @return Command|null
     */
    public function getRoot() {
        return $this->getChain()[0];
    }

    /**
     * @return Command[]
     */
    public function getChain() : array {
        $chain = [$this];
        if(!$this->isChild()) return $chain;
        $parent = $this->parent;
        $chain[] = $parent;
        while($parent->isChild()) {
            $parent = $parent->getParent();
            $chain[] = $parent;
        }
        return array_reverse($chain);
    }

    /**
     * @return Command[]
     */
    public function getChildsByToken(string $token) : array
    {
        $matches = [];
        foreach($this->childs as $child) {
            if($token === ($name = $child->getName())) {
                $matches[] = $child;
                break;
            }
            $hay = [0 => $name];
            $hay = array_merge($child->getAliases(), $hay);
            foreach($hay as $al) {
                if(($p = strpos($al, $token)) === 0) {
                    $matches[$al] = $child;
                    break;
                }
            }
        }
        ksort($matches);
        return array_values($matches);
    }

	/**
	 * Registers new subcommand or replaces existing one
	 * 
	 * @param Command $command
	 * @param int $index if null then the child will be added at the end of array
	 */
	public function addChild(Command $command, int $index = null) {
		if($this->contains($command)) 
			throw new \InvalidArgumentException("command '{$command->getName()}' is already a child of '{$this->getName()}'");
		if($this->getParent() === $command)
			throw new \LogicException("parent can not be child");
		if($command->contains($this))
			throw new \LogicException("parent '{$command->getName()}' can't be child of child");
		$this->childs[($index ?? count($this->childs))] = $command;
		$command->setParent($this);
	}

	/**
	 * @var Command[]
	 */
	public function addChilds(array $childs) {
		foreach($childs as $child) {
			$this->addChild($child);
		}
	}

	/**
	 * @var Command[]
	 */
	public function setChilds(array $childs) {
		foreach($this->childs as $child) {
			$this->removeChild($child);
		}
		foreach($childs as $child) {
			$this->addChild($child);
		}
	}

	public function contains(Command $command) : bool {
		return in_array($command, $this->childs, true);
	}

	public function removeChild(Command $command) {
		if($this->contains($command)) {
			unset($this->childs[array_search($command, $this->childs, true)]);
			$command->setParent(null);
		}
	}

	public function getChilds() : array {
		return $this->childs;
	}

	public function isChild() : bool {
		return $this->parent instanceof Command;
	}

	public function isParent() : bool {
		return !empty($this->childs);
	}

	public function getParent() {
		return $this->parent;
	}

	public function setParent(Command $command) {
		if($this === $command) throw new \LogicException("command can not be parent of self");
		// TODO: other logic checks
		$this->parent = $command;
		$command->removeChild($command);
	}

	/*
	 * ----------------------------------------------------------
	 * ARGUMENTS
	 * ----------------------------------------------------------
	 */

	public function addArgument(Argument $arg) {
		$this->arguments[] = $arg;
	}

	public function removeArgument(Argument $arg) {
		if(($i = $this->getArgumentIndex($arg)) >= 0) {
			unset($this->arguments[$i]);
		}
	}

	public function getArgumentIndex(Argument $arg) : int {
		foreach($this->arguments as $i => $a) {
			if($a === $arg) return $i;
		}
		return -1;
	}

	public function readArgument(int $index, string $input = null) {
		// What a useless function is here :/
		if(isset($this->values[$index])) return $this->values[$index];
		if($input) {
			if(isset($this->arguments[$index])) {
				$this->values[$index] = $this->arguments[$index]->read($input);
			}
		}
	}

	public function readArguments() {
		foreach ($this->arguments as $key => $arg) {
			if(!isset($this->args[$key])) return false;
			$this->readArgument($index, $this->args[$key]);
		}
		return true;
	}

	public function getRequiredArgumentCount() : int {
		$i = 0;
		foreach ($this->arguments as $a) {
			if($a->isRequired($this->sender)) $i++;
		}
		return $i;
	}

	/*
	 * ----------------------------------------------------------
	 * REQUIREMENTS
	 * ----------------------------------------------------------
	 */

	public function hasRequirement(Requirement $r) {
		return in_array($r, $this->requirements, true);
	}

	public function addRequirement(Requirement $r) {
		$this->requirements[] = $r;
	}

	public function testRequirements(CommandSender $sender = null) : bool {
		$sender = $sender ?? $this->sender;
		foreach($this->requirements as $requirement) {
			if(!$requirement->hasMet($r, false)) return false;
		}
		return true;
	}

	/*
	 * ----------------------------------------------------------
	 * USAGE
	 * ----------------------------------------------------------
	 * 
	 * Generate dynamic usage messages 
	 *
	 */

	public function getUsage() {
		$sender = $this->sender;
		$usage = "/";
        // add chain
        $chain = $this->getChain();
        array_reverse($chain);
        foreach($chain as $cmd) {
            $usage .= $cmd->getName()." ";
        }
        foreach ($this->arguments as $param) {
            $usage .= $param->getTemplate($sender)." ";
        }
        $usage = trim($usage);
        $this->usage = $usage;
        return $usage;
	}

	public function sendUsage(CommandSender $sender = null) {
		$sender = $sender ?? $this->sender;
		$sender->sendMessage($this->getUsage());
	}

	/*
	 * ----------------------------------------------------------
	 * EXECUTION
	 * ----------------------------------------------------------
	 */

	public function execute(CommandSender $sender, $label, array $args) {
		$this->sender 	= $sender;
		$this->label 	= "/" . $this->getName() . " " . implode(" ", $args);
		$this->args 	= $args;

		if(!$this->testPermission($sender)) {
			$sender->sendMessage($this->getPermissionMessage());
			return false;
		}
		if(!$this->testRequirements()) {
			return false;
		}
		if( ($argCount = count($args)) < $this->getRequiredArgumentCount() ) {
            $this->sendUsage($sender);
            return false;
        }

        $stop = false;
        foreach ($this->arguments as $i => $param) {

            if (!isset($args[$i]) and !$param->isDefaultValueSet()) {
            	break;
            }

            $value = isset($args[$i]) ? $args[$i] : $param->getDefaultValue();

            if(Argument::validateInputType($value, $param->getType())) {
                $param->setValue($param->read($value, $sender, false));
            } else {
                $stop = true;
                $sender->sendMessage($param->createErrorMessage($sender, $value));
                break;
            }
            $this->values[$i] = $value;
        }
        if($stop) return false;

        if (!empty($this->childs) and count($this->values) > 0) {
            if($this->values[0] === "") {
                $this->sendUsage($sender);
                return false;
            }
            $matches = $this->getChildsByToken((string) $this->values[0]);

            if( ($matchCount = count($matches)) === 1 ) {
                array_shift($args);
                $matches[0]->execute($sender, $label, $args);
                $this->endPoint = $matches[0]->endPoint;
                return true;
            } else {
            	$this->endPoint = $this;
            	// Token was too ambiguous
                if($matchCount > 8) {
                    $sender->sendMessage(Localizer::trans("command.too-ambiguous", [$this->values[0]]));
                    return false;
                }
                // No commands by token was found
                if($matchCount === 0) {
                    $sender->sendMessage(Localizer::trans("command.child-none", [$this->values[0]]));
                    return false;
                }
                // Few commands by token was found an suggestion table will be created
                $sender->sendMessage(Localizer::trans("command.suggestion.header", [$this->values[0]]));
                foreach($matches as $match) {
                    $sender->sendMessage(Localizer::trans("command.suggestion", [$match->getName(), $match->getUsage($sender), $match->getDescription()]));
                }
                return false;
            }
        }

        $this->reset();
        return true;
	}

	public function reset() {
		$this->sender = null;
		$this->label = "";
		$this->args = [];
		$this->values = [];
	}

	public function getPlugin() {
		return $this->plugin;
	}

}