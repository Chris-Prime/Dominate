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
namespace dominate\requirement;

use pocketmine\command\CommandSender;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\Player;

class SimpleRequirement extends Requirement {

	public function __construct() {

	}

	/*
	 * ----------------------------------------------------------
	 * ABSTRACT
	 * ----------------------------------------------------------
	 */

	public abstract function hasMet(CommandSender $sender) : bool {
		switch ($this->type) {
			case self::OP:
				return ($sender instanceof Player) ? $sender->isOp() : true;
			case self::NOT_OP:
				return ($sender instanceof Player) ? !$sender->isOp() : true;
			case self::ALIVE:
				return ($sender instanceof Player) ? $sender->isAlive() : false;
			case self::DEAD:
				return ($sender instanceof Player) ? !$sender->isAlive() : false;
			case self::PLAYER:
				return ($sender instanceof Player) ? true : false;
			case self::CONSOLE:
				return ($sender instanceof ConsoleCommandSender) ? true : false;
			default:
				return false;
		}
	}

}