<?php

namespace WMultiWorld;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\level\Level;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\math\Vector3;
use WMultiWorld\CallBackTask;
use pocketmine\level\Position;
use pocketmine\event\entity\EntityLevelChangeEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\BlockBreakEvent;
use onebone\economyapi\EconomyAPI;
use pocketmine\scheduler\Task;
use pocketmine\level\generator\Generator;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\Server;

class WMultiWorld extends PluginBase implements Listener 
{
    public function onEnable() 
    {
		@mkdir($this->getDataFolder());
		Generator::addGenerator(Land::class, "land");
		Generator::addGenerator(SnowLand::class, "snowland");
		Generator::addGenerator(EmptyWorld::class, "empty");
		Generator::addGenerator(WoodFlat::class, "woodflat");
		$this->config=new Config($this->getDataFolder()."config.yml",Config::YAML,array(
		"manager" => array(),
		"tp-msg" => "§b[WMultiWorld] 你被传送到了世界 {world}",
		"noexist-msg" => "§b[WMultiWorld] 对不起，世界 {world} 不存在！",
		"noname-msg" => "§b[WMultiWorld] 请输入一个地图名",
		"notplayer-msg" => "§b[WMultiWorld] 请在游戏内输入传送指令！",
		"chat-tp" => array(),
		"item-touch"=>array(259,325,351,291,292,293,294),
		"protect-world" => array(),
		"protect-msg" => "§c对不起，这个世界被保护了！",
		"banpvp-world" => array(),
		"op-pvp" => "false",
		"world-create" => array(),
		"whitelist-world" => array(),
		"wl-list" => array(),
		"pvp-msg" => "§e=====玩家信息=====%n§aID: {name}%n§b金钱: {money}%n§b饥饿值: {food}%n§b血量: [{hp}/{mhp}]%n§b权限: {isop}"
		));
		$this->temp=new Config($this->getDataFolder()."temp.wtp",Config::YAML,array(
		"pig" => "0",
		"name" => "",
		"level" => "",
		"empty-type" => array(
		"radius" => 20,
		"block" => 20
		)
		));
        $this->getServer()->getLogger()->info("§aWhale的多世界！");
        $this->LoadAllLevels();
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
    }
    public function LoadAllLevels() 
    {
        $level = $this->getServer()->getDefaultLevel();
        $path = $level->getFolderName();
        $p1 = dirname($path);
        $p2 = $p1."/worlds/";
        $dirnowfile = scandir($p2, 1);
        foreach ($dirnowfile as $dirfile)
        {
            if($dirfile != '.' && $dirfile != '..' && $dirfile != $path && is_dir($p2.$dirfile)) 
            {
                if (!$this->getServer()->isLevelLoaded($dirfile))
                {  //如果这个世界未加载
                    $this->getLogger()->info("正在加载世界：$dirfile");
                    //$this->getServer()->generateLevel($dirfile);
                    $this->getServer()->loadLevel($dirfile);
                    $level = $this->getServer()->getLevelbyName($dirfile);
                    if ($level->getName() != $dirfile) 
                    {  //温馨提示
                        $this->getLogger()->info("[WMultiWorld]您加载的地图 $dirfile 的文件夹名与地图名 ".$level->getName()." 不符，可能会出现一些奇怪的bug！如世界保护插件不能保护地图等问题\n修复可输入/lvdat");
                    }
                }
            }
        }
    }
	
	public function onChat(PlayerChatEvent $event)
	{
		$tplist=$this->config->get("chat-tp");
		$temp=$this->temp->get("pig");
		$tempname=$this->temp->get("name");
		if($temp == "1" && ($tempname == $event->getPlayer()->getName()))
		{
			$world=$this->temp->get("level");
			$msg=$event->getMessage();
			$tplist[$world]=$msg;
			$this->config->set("chat-tp",$tplist);
			$this->config->save();
			$this->temp->set("pig","0");
			$this->temp->set("name","");
			$this->temp->save();
			$event->getPlayer()->sendMessage("§a[WMultiWorld] 成功设置世界 $world 的快速传送指令为 $msg , 玩家可以直接在聊天框输入此文字传送到世界 $world ");
			$event->setCancelled(true);
			return true;
		}
		$msg=$event->getMessage();
		foreach($tplist as $a=>$b)
		{
			if($msg == $b)
			{
				if ($this->getServer()->isLevelLoaded($a)) 
				{ 
					$event->getPlayer()->teleport($this->getServer()->getInstance()->getLevelByName($a)->getSafeSpawn());
					$event->getPlayer()->sendMessage(str_replace("{world}",$a,$this->config->get("tp-msg")));
				}
				else
				{
					$event->getPlayer()->sendMessage( str_replace("{world}",$a,$this->config->get("noexist-msg")));
				}
				$event->setCancelled(true);
				break;
			}
		}
	}
    public function onCommand(CommandSender $sender, Command $cmd, $label, array $args)
    {
		
        switch($cmd->getName())
        {
			case "setworld":
			$this->config->reload();
				if(isset($args{0}))
				{
					switch($args[0])
					{
						case "chat":
							if(isset($args[1]))
							{
								$world=$args[1];
								if(!$this->getServer()->isLevelGenerated($world))
								{
									$sender->sendMessage("§c[WMultiWorld]对不起，地图 $world 不存在！无法为其设置快速传送命令！");
									return true;
								}
								$this->temp->set("pig","1");
								$this->temp->set("name",$sender->getName());
								$this->temp->set("level",$world);
								$this->temp->save();
								$sender->sendMessage("§a[WMultiWorld] 成功进入快速指令设置模式！请在聊天框直接输入需要被设置的聊天文字！");
								return true;
							}
							else
							{
								$sender->sendMessage("§c[WMultiWorld] 用法： /setworld chat [地图名]");
								return true;
							}
						case "unload":
							if(isset($args[1]))
							{
								$l = $args[1];
								if (!$this->getServer()->isLevelLoaded($l)) 
								{  //如果这个世界未加载
									$sender->sendMessage("§c[WMultiWorld] 地图 $l 未被加载 , 无法卸载");
								}
								else 
								{
									$level = $this->getServer()->getLevelbyName($l);
									$ok = $this->getServer()->unloadLevel($level); 
									if($ok !== true)
									{
										$sender->sendMessage("§c[WMultiWorld] 卸载地图 $l 失败 ！ ");
									}
									else
									{
										$sender->sendMessage("§a[WMultiWorld] 地图 $l 已被成功卸载 ！ ");
									}
								}
							}
							else
							{
								$sender->sendMessage("§c[WMultiWorld] 用法： /setworld unload [地图名]");
							}
							return true;
						case "gm":
							if(isset($args[1]))
							{
								$worldname=$args[1];
								$database=$this->config->get("world-create");
								if(in_array($worldname,$database))
								{
									$inv=array_search($worldname,$database);
									$inv=array_splice($database,$inv,1);
									$this->config->set("world-create",$database);
									$this->config->save();
									$sender->sendMessage("§a[WMultiWorld] 成功将世界 $worldname 从创造地图列表删除！");
									return true;
								}else
								{
									$database[]=$worldname;
									$this->config->set("world-create",$database);
									$this->config->save();
									$sender->sendMessage("§a[WMultiWorld] 成功将世界 $worldname 加入创造地图列表！");
									return true;
								}
							}
							else
							{
								$sender->sendMessage("§c[WMultiWorld] 用法： /setworld gm <地图名>: 将地图设置为创造模式或者生存模式");
							    return true;
							}
						case "wl":
							if(isset($args[1]))
							{
								switch($args[1])
								{
									case "add":
										if(isset($args[2]))
										{
											$at=$args[2];
											$t=$this->config->get("whitelist-world");
											if(isset($t[$at])){$sender->sendMessage("§c[WMultiWorld] 对不起，这个世界已经开启白名单了！");return true;}
											$t[$at]["player"]=array();
											$t[$at]["ban-msg"]="§c对不起，你没有权限进入世界{world}";
											$this->config->set("whitelist-world",$t);
											$this->config->save();
											$sender->sendMessage("§a[WMultiWorld] 成功开启世界 $at 的白名单");
											return true;
										}else{$sender->sendMessage("§c[WMultiWorld] 用法： /setworld wl add <地图名>");return true;}
									case "del":
										if(isset($args[2]))
										{
											$at=$args[2];
											$t=$this->config->get("whitelist-world");
											if(!isset($t[$at])){$sender->sendMessage("§c[WMultiWorld] 对不起，这个世界还没有开启白名单");return true;}
											unset($t[$at]);
											$this->config->set("whitelist-world",$t);
											$this->config->save();
											$sender->sendMessage("§a[WMultiWorld] 成功关闭世界 $at 的白名单");
											return true;
										}
									
									default:
										$sender->sendMessage("§e用法：/setworld wl <add/del>");return true;
								}
							}
							else{$sender->sendMessage("§e用法：/setworld wl <add/del>");return true;}
						case "load":
							if(isset($args[1]))
							{
								$level = $this->getServer()->getDefaultLevel();
								$path = $level->getFolderName();
								$p1 = dirname($path);
								$p2 = $p1."/worlds/";
								$path = $p2;
								$l = $args[1];
								if ($this->getServer()->isLevelLoaded($l)) 
								{  //如果这个世界已加载
									$sender->sendMessage("§c[WMultiWorld] 地图 ".$args[1]." 已被加载 , 无法再次加载" );
								}
								elseif (is_dir($path.$l))
								{
									$sender->sendMessage("§b[WMultiWorld] 正在加载地图 ".$args[1]."." );
									$this->getServer()->generateLevel($l);
									$ok = $this->getServer()->loadLevel($l);
									if ($ok === false) 
									{
										$sender->sendMessage("§c[WMultiWorld] 地图 ".$args[1]." 加载失败");
									}
									else 
									{
										$sender->sendMessage("§c[WMultiWorld] 地图 ".$args[1]." 加载成功");
									}
								}
								else
								{
									$sender->sendMessage("§c[WMultiWorld] 无法加载地图 ".$args[1]." , 地图文件不存在");
								}
							}
							else
							{
								$sender->sendMessage("§c[WMultiWorld] 用法： /setworld load [地图名]");
							}
							return true;
						case "setwl":
							if(isset($args[1]))
							{
								$world=$args[1];
								$list=$this->config->get("whitelist-world");
								if(!isset($list[$world])){$sender->sendMessage("§c对不起，这个名字的世界还没有开启白名单！");return true;}
								if(isset($args[2]))
								{
									$name=strtolower($args[2]);
									$pl=$list[$world]["player"];
									if(in_array($name,$pl))
									{
										$inv=array_search($name,$pl);
										$inv=array_splice($pl,$inv,1);
										$list[$world]["player"]=$pl;
										$this->config->set("whitelist-world",$list);
										$this->config->save();
										$sender->sendMessage("§a[WMultiWorld] 成功将玩家 $name 从世界 $world 的白名单移出！");
										return true;
									}
									else
									{
										$pl[]=$name;
										$list[$world]["player"]=$pl;
										$this->config->set("whitelist-world",$list);
										$this->config->save();
										$sender->sendMessage("§a[WMultiWorld] 成功将玩家 $name 加入世界 $world 的白名单！");
										return true;
									
									}
								}else{$sender->sendMessage("§e[WMultiWorld] 用法: /setworld setwl <地图名> <玩家ID>");return true;}
							}else{$sender->sendMessage("§e[WMultiWorld] 用法: /setworld setwl <地图名> <玩家ID>");return true;}
							
						case "delmap":
							$this->config->reload();
							if(!in_array($sender->getName(),$this->config->get("manager")) && $sender instanceof Player)
							{
								$sender->sendMessage("对不起，此操作为风险操作，需要配置文件中授权的玩家或控制台才可以使用！请到配置文件修改manager玩家ID，然后再次输入这个指令！");
								return true;
							}
							if(isset($args[1]))
							{
								$map=$args[1];
								if(!isset($args[2]))
								{
									$sender->sendMessage("§c警告： 确认要删除地图 $map 的存档吗？此操作会永久使地图存档 $map 丢失！\n§e确认操作请输入/setworld delmap $map yes");
									return true;
								}
								elseif($args[2] == "yes")
								{
									if($this->getServer()->isLevelGenerated($map) && !$this->getServer()->isLevelLoaded($map))
									{
										$dirs="worlds/".$map;
										$like=$this->delMapData($dirs);
										if($like === true)
										{
											$sender->sendMessage("§a删除地图 $map 成功！");
										}
										else
										{
											$sender->sendMessage("§e删除地图 $map 失败！");
										}
										return true;
									}
									else
									{
										$sender->sendMessage("§c错误！地图已被加载或不存在此地图！如果地图已经被加载，请先输入/setworld unload 来卸载当前地图！");
										return true;
									}
								}
							}
							else
							{
								$sender->sendMessage("§6=====清除地图存档功能=====\n§a/setworld delmap [地图名]");
								return true;
							}
						case "protect":
							if(isset($args[1]))
							{
								$levels=$this->config->get("protect-world");
								$level=$args[1];
								if(in_array($level,$levels))
								{
									$inv = array_search($level, $levels);
									$inv = array_splice($levels, $inv, 1); 
									$this->config->set("protect-world",$levels);
									$this->config->save();
									$sender->sendMessage("§a[WMultiWorld] 成功取消保护世界 $level");
									return true;
								}
								else
								{
									$levels[]=$level;
									$this->config->set("protect-world",$levels);
									$this->config->save();
									$sender->sendMessage("§a[WMultiWorld] 成功保护世界 $level");
									return true;
								}
							}
							else{$sender->sendMessage("§c[WMultiWorld] 用法: /setworld protect [地图名]");return true;}
						case "pvp":
							if(isset($args[1]))
							{
								switch($args[1])
								{
									case "world":
										if(isset($args[2]))
										{
											$levels=$this->config->get("banpvp-world");
											$level=$args[2];
											if(in_array($level,$levels))
											{
												$inv = array_search($level, $levels);
												$inv = array_splice($levels, $inv, 1); 
												$this->config->set("banpvp-world",$levels);
												$this->config->save();
												$sender->sendMessage("§a[WMultiWorld] 已允许世界 $level 进行PVP！");
												return true;
											}
											else
											{
												$levels[]=$level;
												$this->config->set("banpvp-world",$levels);
												$this->config->save();
												$sender->sendMessage("§a[WMultiWorld] 成功禁止世界 $level 的PVP！");
												return true;
											}
										}
										else
										{
											$sender->sendMessage("§c[WMultiWorld] 用法: /setworld pvp world [地图名]: 添加或删除一个世界的禁止PVP");
											return true;
										}
									case "oppvp":
										if(isset($args[2]))
										{
											switch($args[2])
											{
												case "true":
													$this->config->set("op-pvp","true");
													$this->config->save();
													$sender->sendMessage("§a[WMultiWorld] 已允许op在禁止PVP的世界PVP！");
													return true;
												case "false":
													$this->config->set("op-pvp","false");
													$this->config->save();
													$sender->sendMessage("§a[WMultiWorld] 已禁止op在禁止PVP的世界PVP！");
													return true;
												default:
													$sender->sendMessage("§c[WMultiWorld] 用法: /setworld pvp oppvp [true/false]");
													return true;
											}
										}
										else{$sender->sendMessage("§c[WMultiWorld] 用法: /setworld pvp oppvp [true/false]");return true;}
								}
							}
							else{$sender->sendMessage("§e=====WMultiWorld=====\n§a/setworld pvp world [地图名]: 添加或删除禁止PVP的世界\n§a/setworld pvp oppvp: 允许或禁止op进行PVP");return true;}
						case "admin":
							if($sender instanceof Player)
							{
								$sender->sendMessage("§c[WMultiWorld]对不起，添加删除多世界管理员仅限控制台！");
								return true;
							}
							if(isset($args[1]))
							{
								$name=$args[1];
								$managers=$this->config->get("manager");
								if(in_array($name,$managers))
								{
									$inv = array_search($name, $managers);
									$inv = array_splice($managers, $inv, 1); 
									$this->config->set("manager",$managers);
									$this->config->save();
									$sender->sendMessage("§a[WMultiWorld] 成功将玩家 $name 的管理员取消！");
									
									return true;
								}
								else
								{
									$managers[]=$name;
									$this->config->set("manager",$managers);
									$this->config->save();
									$sender->sendMessage("§a[WMultiWorld] 成功将玩家 $name 设置为多世界管理员！");
									
									return true;
								}
							}
							else{$sender->sendMessage("§c[WMultiWorld] 用法: /setworld admin [玩家ID]");return true;
							}
					}
				}
				else
				{
					$sender->sendMessage("§6=====地图配置选项=====\n§a/setworld load [地图名]: §b加载已安装的地图\n§a/setworld unload [地图名]: §b卸载一个已加载的地图\n§a/setworld wl: §b添加或删除一个世界的白名单\n§a/setworld chat [地图名]: §b设置一个快速传送指令\n§a/setworld delmap [地图名]: §b删除一个地图的存档\n§a/setworld protect [地图名]: §b添加或删除一个保护的世界\n§a/setworld pvp oppvp: §b设置op在禁止PVP的世界的权限\n§a/setworld pvp world: §b设置禁止PVP的世界\n§a/setworld admin: §b设置多世界管理员( 仅限后台)\n§a/setworld setwl: §b添加或删除世界白名单的玩家");
					return true;
				}
     		case "lw":
				$levels = $this->getServer()->getLevels();
				$sender->sendMessage("§6==== 地图列表 ====");
				foreach ($levels as $level)
				{
				    $name[]=$level->getFolderName();
				}
				$sender->sendMessage("§b".implode(", ",$name));
				return true;
			case "w":
			    if ($sender instanceof Player)
				{
				    if(isset($args[0]))
				    {
				        $l = $args[0];
				        if ($this->getServer()->isLevelLoaded($l)) 
				        {
							$sender->teleport($this->getServer()->getInstance()->getLevelByName($l)->getSafeSpawn());
				            $sender->sendMessage(str_replace("{world}",$l,$this->config->get("tp-msg")));
				        }
				        else
				        {
				            $sender->sendMessage( str_replace("{world}",$l,$this->config->get("noexist-msg")));
				        }
				    }
				    else
				    {
				        $sender->sendMessage($this->config->get("noname-msg"));
				    }
			    }
				else
			    {
				    $sender->sendMessage($this->config->get("notplayer-msg"));
				}
				return true;
			case "makemap":
				if(isset($args[0]))
				{
					$name=$args[0];
					if($this->getServer()->isLevelGenerated($name))
					{
						$sender->sendMessage("§c[WMultiWorld] 对不起，此地图已经加载，请换个名字生成！");
						return true;
					}
					if(isset($args[1]))
					{
						switch($args[1])
						{
							case "default":
								if(isset($args[2]))
								{
									$seed=$args[2];
									$opts=[];
									$gen=Generator::getGenerator("default");
									$sender->sendMessage("§b[WMultiWorld] 正在生成地图 $name 中，类型是原生世界，可能会卡顿");
									$this->getServer()->generateLevel($name,$seed,$gen,$opts);
									$this->getServer()->loadLevel($name);
									$sender->sendMessage("§a[WMultiWorld] 成功生成地图！");
									return true;
								}
								else
								{$sender->sendMessage("§c[WMultiWorld] 用法： /makemap [地图名] default [种子]");return true;}
							case "flat":
								if(isset($args[2])){$opts=$args[2];}
								else{$opts=[];}
								$seed=1;
								$gen=Generator::getGenerator("Flat");
								$sender->sendMessage("§b[WMultiWorld] 正在生成地图 $name 中，类型是超平坦，可能会卡顿");
								$this->getServer()->generateLevel($name,$seed,$gen,$opts);
								$this->getServer()->loadLevel($name);
								$sender->sendMessage("§a[WMultiWorld] 成功生成地图！");
								return true;
							case "empty":
								if(isset($args[2])){$tsp=$this->temp->get("empty-type");$opts=$tsp;}
								else{$opts=[];}
								$seed=1;
								$gen=Generator::getGenerator("empty");
								$sender->sendMessage("§b[WMultiWorld] 正在生成地图 $name 中，类型是空地图，可能会卡顿");
								$this->getServer()->generateLevel($name,$seed,$gen,$opts);
								$this->getServer()->loadLevel($name);
								$sender->sendMessage("§a[WMultiWorld] 成功生成地图！");
								return true;	
							case "land":
								$opts=[];
								$seed=1;
								$gen=Generator::getGenerator("land");
								$sender->sendMessage("§b[WMultiWorld] 正在生成地图 $name 中，类型是普通地皮，可能会卡顿");
								$this->getServer()->generateLevel($name,$seed,$gen,$opts);
								$this->getServer()->loadLevel($name);
								$sender->sendMessage("§a[WMultiWorld] 成功生成地图！");
								return true;
							case "snowland":
								$opts=[];
								$seed=1;
								$gen=Generator::getGenerator("snowland");
								$sender->sendMessage("§b[WMultiWorld] 正在生成地图 $name 中，类型是雪地地皮，可能会卡顿");
								$this->getServer()->generateLevel($name,$seed,$gen,$opts);
								$this->getServer()->loadLevel($name);
								$sender->sendMessage("§a[WMultiWorld] 成功生成地图！");
								return true;
							default:
								$m=$this->setNewLevel($name,$args[1]);
								if($m === true)
								{
									$sender->sendMessage("§a[WMultiWorld] 成功生成地图! 地图类型为 $args[1]");
								}
								elseif($m === 1)
								{
									$sender->sendMessage("§c[WMultiWorld] 未知的生成器类型，请使用预设的生成器！");
								}
								elseif($m === false)
								{
									$sender->sendMessage("§c[WMultiWorld] 对不起，此地图已经加载，请换个名字生成！");
								}
								return true;
						}
				    }
					else{$sender->sendMessage("§c[WMultiWorld] 用法： /makemap [地图名] [类型]");return true;}
				}
				else
				{
					$sender->sendMessage("§6=====地图生成器=====\n§a指令: /makemap [地图名] [类型]\n§b类型包括以下几种：\n§edefault: 原生世界\n§eflat: 超平坦\n§eempty: 空白世界\n§eland: 普通地皮\n§esnowland: 雪地地皮\n§ewoodflat: 平坦木头区");
					return true;
				}
			
		}
	}
	
	public function onHurt(EntityDamageEvent $eventp)
	{
		if($eventp instanceof EntityDamageByEntityEvent)
		{
			$this->checkPvP($eventp);
		}
	}
	public function delMapData($dir) 
	{
		$dh = opendir($dir);
		while ($file=readdir($dh))
		{
			if($file!="." && $file!="..") 
			{
				$fullpath = $dir."/".$file;
				if(!is_dir($fullpath))
				{
					@unlink($fullpath);
				}
				else
				{
					$this->delMapData($fullpath);
				}
			}
		}
		closedir($dh);
		if(@rmdir($dir)) 
		{
			return true;
		} 
		else 
		{
			return false;
		}
	}
	public function resetMap($levelname)
	{
		if($this->getServer()->isLevelLoaded($levelname))
		{
			$lv=$this->getServer()->getLevelbyName($levelname);
			$stat=$this->getServer()->unloadLevel($lv);
			if($stat !== true){return 001;}
		}
		elseif(!$this->getServer()->isLevelGenerated($levelname))
		{
			return 003;
		}
		$dirs="worlds/".$levelname;
		$like=$this->delMapData($dirs);
		if($like !== true){return 002;}
		$gen=Generator::getGenerator("default");
		$opts=[];
		$seed=rand(1,9000);
		$this->getServer()->generateLevel($levelname,$seed,$gen,$opts);
		$this->getServer()->loadLevel($levelname);
		return true;
	}
	public function setNewLevel($level,$type)
	{
		if(!$this->getServer()->isLevelGenerated($level))
		{
			$seed=1;
			$opts=[];
			if(!in_array($type,Generator::getGeneratorList()))
			{
				return 1;
			}
			$gen=Generator::getGenerator($type);
			$this->getServer()->generateLevel($level,$seed,$gen,$opts);
			$this->getServer()->loadLevel($level);
			return true;
		}
		else
		{
			return false;
		}
	}
	public function NoEnter()
	{
		$p=$this->plus;
		$lv=$this->banlevel;
		$p->teleport($lv->getSafeSpawn());
		$this->tickreset->remove();
	}
	public function getGenerators()
	{
		return Generator::getGeneratorList();
	}
	public function onTeleport(EntityTeleportEvent $event)
	{
		if($event->getEntity() instanceof Player)
		{
			$player=$event->getEntity();
			$target=$event->getTo();
			$level=$target->level->getFolderName();
			$list=$this->config->get("whitelist-world");
			if(isset($list[$level]["player"]))
			{
				$player=strtolower($event->getEntity()->getName());
				if((!in_array($player,$list[$level]["player"])) && (!$event->getEntity()->isOp()))
				{
					$event->setCancelled(true);
					$event->getEntity()->sendMessage(str_replace("{world}",$lv,$list[$lv]["ban-msg"]));
				}
			}
		}
	}
	public function changeLevel(EntityLevelChangeEvent $event)//禁止进入世界飞行的功能（世界切换事件）
	{
		$lv=$event->getTarget()->getFolderName();
		if($event->isCancelled())
		{
			return;
		}
		if(in_array($lv,$this->config->get("world-create")))
		{
			$ent=$event->getEntity();
			if($ent instanceof Player)
			{
				$ent->setGamemode(1);
				$ent->sendTip("§a你已经切换为创造模式！");
			}
		}
		else
		{
			$ent=$event->getEntity();
			if($ent instanceof Player)
			{
				if($or=$ent->getGamemode() == 0)
					return;
				else
				{
					$ent->setGamemode(0);
					$ent->sendTip("§b你已经切换为生存模式！");
				}
				
			}
		}
	}
	public function playerblockBreak(BlockBreakEvent $event) {$this->checkPerm($event);}
	public function PlayerPlaceBlock(BlockPlaceEvent $event) {$this->checkPerm($event);}
	public function playerinteract(PlayerInteractEvent $event){$player = $event->getPlayer();$user = $player->getName();$itemid = $event->getItem()->getID();$itemtouch = $this->config->get("item-touch");if(in_array($itemid,$itemtouch)){$this->checkPerm($event);}}
	public function checkPerm($event)
	{
		$player = $event->getPlayer();
		$user = $player->getName();
		$level = $player->getLevel()->getFolderName();
		$pw=$this->config->get("protect-world");
		$admin=$this->config->get("manager");
		$msg=$this->config->get("protect-msg");
		if((in_array($level,$pw)) and (!in_array($user,$admin)))
		{
			$player->sendTip($msg);
			$event->setCancelled(true);
		}
	}
	public function checkPvP($eventp)
	{
		if(($eventp->getDamager() instanceof Player) && ($eventp->getEntity() instanceof Player))
		{
			$level=$eventp->getDamager()->getLevel()->getFolderName();
			$isop=$eventp->getDamager()->isOp() ? "yes" : "no";
			if(in_array($level,$this->config->get("banpvp-world")))
			{
				if($isop == "yes" && $this->config->get("op-pvp")=="false")
				{
					$eventp->setCancelled(true);
					$eventp->getDamager()->sendMessage($this->msgs($eventp->getEntity(),$this->config->get("pvp-msg")));
				}
				elseif($isop == "no")
				{
					$eventp->setCancelled(true);
					$eventp->getDamager()->sendMessage($this->msgs($eventp->getEntity(),$this->config->get("pvp-msg")));
				}
			}
		}
	}
	public function msgs($p,$msg)
	{
		if($this->getServer()->getPluginManager()->getPlugin("EconomyAPI") !== null)
		{
			$m = EconomyAPI::getInstance()->myMoney($p->getName());
			$msg=str_replace("{money}",$m,$msg);
		}
		$food=$p->getFood();
		$isop=$p->isOp() ? "管理员" : "玩家";
		$msg=str_replace("%n","\n",$msg);
		$msg=str_replace("+"," ",$msg);
		$msg=str_replace("{name}",$p->getName(),$msg);
		$msg=str_replace("{hp}",$p->getHealth(),$msg);
		$msg=str_replace("{mhp}",$p->getMaxHealth(),$msg);
		$msg=str_replace("{food}",$food,$msg);
		$msg=str_replace("{isop}",$isop,$msg);
		return $msg;
	}
}
class CallbackTask extends Task//任务class
{
	protected $callable;
	protected $args;
	
	public function __construct(callable $callable, array $args = [])
	{
		$this->callable = $callable;
		$this->args = $args;
		$this->args[] = $this;
	}
	
	public function getCallable()
	{
		return $this->callable;
	}

	public function onRun($currentTicks)
	{
		\call_user_func_array($this->callable, $this->args);
	}
}
