<?php

namespace NeosPrefix;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;

use pocketmine\utils\Internet;
use pocketmine\utils\Config;

use pocketmine\Player;
use pocketmine\Server;

use pocketmine\item\Item;
use pocketmine\block\Block;

use pocketmine\level\Position;

use pocketmine\math\Vector3;
use pocketmine\utils\TextFormat;

use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\command\PluginCommand;

use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;

use pocketmine\event\player\PlayerChatEvent;

use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\SignChangeEvent;

use pocketmine\event\player\PlayerInteractEvent;

use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\ServerSettingsRequestPacket;
use pocketmine\network\mcpe\protocol\ModalFormRequestPacket;
use pocketmine\network\mcpe\protocol\ServerSettingsResponsePacket;
use pocketmine\network\mcpe\protocol\ModalFormResponsePacket;

use pocketmine\nbt\tag\StringTag;

function getStringByPos ($pos)
{

    return $pos->getX() . ':' . $pos->getY() . ':' . $pos->getZ() . ':' . $pos->getLevel()->getFolderName();

}

function getPosByString ($string)
{

    $pos = explode (':', $string);
    return new Position ((float) $pos[0], (float) $pos[1], (float) $pos[2], Server::getInstance()->getLevelByName ($pos[3]));

}

class NeosPrefix extends PluginBase implements Listener
{
	
	public $id = [
	
		1232135,
		1251234,
		2346236,
		1232145,
		8123949,
		1235323,
		2321331,
		3245643,
		1928421
		
	];

	public function onEnable()
	{

		$serverLang = (new Config(\pocketmine\DATA . "server.properties", Config::PROPERTIES))->get ('language');

		$this->database = new Config($this->getDataFolder() . 'config.yml', Config::YAML, [
		
			'플러그인 언어 (language)' => $serverLang,
			'채팅 타입' => '§6 『 §f(칭호) §r§6』 §f(닉네임)§r§f: (채팅색)(채팅)',
			
			'채팅 색' => [
			
				'관리자' => '§6',
				'유저' => '§f'
				
			],
			
			'기본 칭호' => '신입',
			
			'자유 칭호권' => []

		]);
		
		$this->db = $this->database->getAll();
	
		$this->playerbase = new Config($this->getDataFolder() . 'player.yml', Config::JSON);
		$this->player = $this->playerbase->getAll();
		
		$this->shopbase = new Config($this->getDataFolder() . 'shop.json', Config::JSON);
		$this->shop = $this->shopbase->getAll();
		
		$this->signbase = new Config($this->getDataFolder() . 'sign.json', Config::JSON);
		$this->sign = $this->signbase->getAll();
		
		$this->msgbase = new Config($this->getDataFolder() . 'messages.json', Config::JSON, (array) json_decode (Internet::getURL ('https://raw.githubusercontent.com/neoskr/NeosPrefix/master/' . $this->db ['플러그인 언어 (language)'] . '.json')));
		$this->m = $this->msgbase->getAll();
		
		$this->title = $this->m ['UI 타이틀'];
		$this->addCommand (['칭호']);
		
		$pluginManager = $this->getServer()->getPluginManager();
		$pluginManager->registerEvents ($this, $this);
		
		$this->economy = $pluginManager->getPlugin ('EconomyAPI');


	}

	public function addCommand($array)
	{
		
		$commandMap = $this->getServer()->getCommandMap();
		
		foreach ($array as $command) {
			
			$a = new PluginCommand($command, $this);
			$a->setDescription('네오스 칭호 플러그인');
			
			$commandMap->register($command, $a);
			
		}

	}

	public function onDisable()
	{
		
		$this->save();
		
	}
		
    	public function save()
    	{

        	$this->database->setAll($this->db);
		$this->database->save();
		
        	$this->playerbase->setAll($this->player);
		$this->playerbase->save();
		
        	$this->shopbase->setAll($this->shop);
		$this->shopbase->save();

        	$this->signbase->setAll($this->sign);
		$this->signbase->save();
		
	}

	public function msg($player, $msg)
	{
		
		$player->sendMessage ($this->m ['플러그인 칭호'] . $msg);
		
	}
	
	public function allmsg($msg)
	{
		
		$this->getServer()->broadcastMessage ($this->m ['플러그인 칭호'] . $msg);

	}

	public function sendUI($player, $code, $data)
	{
		
		$packet = new ModalFormRequestPacket();
		$packet->formId = $code;
		$packet->formData = json_encode ($data);
		$player->dataPacket ($packet);
		
	}
 
	public function msgUI($player, $msg, $title = '')
	{
		if ($title === '') {
			
			$title = $this->title;
			
		}
		
		$packet = new ModalFormRequestPacket();
		$packet->formId = 9854;
		$packet->formData = json_encode ([
			'type' => 'form',
			'title' => $title,
			'content' => "\n" . $msg . "\n\n\n",
			'buttons' => [
				[
					'text' => $this->m ['시스템 종료하기']
				]
			]
		]);
		
		$player->dataPacket ($packet);
	
	}
	
	public function onJoin(PlayerJoinEvent $event)
	{
		
		$name = strtolower ($event->getPlayer()->getName());
		
		if (! isset ($this->player [$name])) $this->createData ($event->getPlayer());

	}

	public function onSign(SignChangeEvent $event)
	{

		$player = $event->getPlayer();

		if ($event->isCancelled()) return true;
		if ($player->isOp() && $event->getLine(0) === '[칭호상점]') {
			
			$prefix = $event->getLine(1);
			$price = (int) $event->getLine(2);
			
			$this->sign [getStringByPos ($event->getBlock())] = [
			
				'생성 시간' => time(),
				'칭호' => $prefix,
				'가격' => $price
			
			];
			
			foreach ([0,1,2,3] as $index) {

				$event->setLine ($index, str_replace (['(칭호)', '(가격)'], [$prefix, number_format ($price)], $this->m ['칭호 상점'][$index]));

			}
			
			$this->msg ($player, $this->m ['상점 생성 완료']);
			return true;
			
		}
		
	}
	
	public function removeShop(BlockBreakEvent $event)
	{
		
		$player = $event->getPlayer();
		$block = $event->getBlock ();
		
		if ($event->isCancelled()) return true;

		if ($block->getId() == Block::SIGN_POST || $block->getId() == Block::WALL_SIGN) {

			if ($player->isOp() && isset ($this->sign [getStringByPos ($block)])) {
				
				unset ($this->sign [getStringByPos ($block)]);
				$this->msg ($player, $this->m ['상점 제거 완료']);
				
				return true;
				
			}
			
		}

	}
	
	public function signShop(PlayerInteractEvent $event)
	{
		
		$player = $event->getPlayer();
		$block = $event->getBlock ();
		
		if ($event->isCancelled()) return true;

		if ($block->getId() == Block::SIGN_POST || $block->getId() == Block::WALL_SIGN) {

			if (isset ($this->sign [getStringByPos ($block)])) {
				
				$dataBase = $this->sign [getStringByPos ($block)];
				
				$prefix = $dataBase ['칭호'];
				$price = $dataBase ['가격'];
				
				if ($this->hasPrefix ($player, $prefix)) {
					
					$this->msg ($player, $this->m ['이미 칭호 소유']);
					return true;
					
				}
				
				$money = $this->economy->myMoney ($player);
				
				if ($money < $price) {
					
					$this->msg ($player, str_replace (['(가격)', '(내돈)'], [$price, $money], $this->m ['돈 부족']));
					return true;
					
				}
				
				if ($player->isSneaking()) {
					
					$this->economy->reduceMoney ($player, $price);
					$this->addPrefix ($player, $prefix);
					
					$this->msg ($player, str_replace (['(칭호)'], [$prefix], $this->m ['칭호 구매 완료']));
					
					return true;
					
				} else {
					
					$this->msg ($player, $this->m ['웅크리세요']);
					return true;
					
				}
				
			}
			
		}

	}
	
	public function onChat(PlayerChatEvent $event)
	{

		if ($event->isCancelled()) return true;
		
		$player = $event->getPlayer();
		$color = $player->isOp() ? $this->db ['채팅 색']['관리자'] : $this->db ['채팅 색']['유저'];

		$event->setFormat (str_replace ([
		
			'(칭호)',
			'(닉네임)',
			'(채팅색)',
			'(채팅)'
			
		], [
		
			$this->getMainPrefix ($player) ?? '신입',
			$this->getNick ($player) ?? $player->getName(),
			$color,
			TextFormat::clean ($event->getMessage())
			
		], $this->db ['채팅 타입']));

	}

	public function getMainPrefix($player)
	{
		
		$name = strtolower ($player->getName());
		return $this->player [$name]['메인 칭호'] ?? $this->db ['기본 칭호'];

	}

	public function getNick($player)
	{

		$name = $player->getName();
		return $this->player [strtolower ($name)]['닉네임'] ?? $name;

	}

	public function getPrefixs($player)
	{
		
		$name = strtolower ($player->getName());
		return $this->player [$name]['칭호'] ?? [];
		
	}

	public function hasPrefix($player, $prefix)
	{
		
		$name = strtolower ($player->getName());
		
		if (! isset ($this->player [$name])) return false;
		
		if (in_array ($prefix, $this->player [$name]['칭호'])) return true;
		
		return false;
		
	}

	public function setMainPrefix($player, $prefix)
	{
		
		$name = strtolower ($player->getName());
		
		if (! isset ($this->player [$name])) $this->createData ($player);
		$this->player [$name]['메인 칭호'] = $prefix;

	}

	public function setNick($player, $name2)
	{

		$name = strtolower ($player->getName());
		
		if (! isset ($this->player [$name])) $this->createData ($player);
		$this->player [$name]['닉네임'] = $name2;
		
	}

	public function addPrefix ($player, $prefix) {
		
		$name = strtolower ($player->getName());
		
		if (! isset ($this->player [$name])) $this->createData ($player);
		array_push ($this->player [$name]['칭호'], $prefix);

	}

	public function addPrefixs($player, $array)
	{
		
		$name = strtolower ($player->getName());
		
		if (! isset ($this->player [$name])) $this->createData ($player);
		foreach ($array as $prefix) array_push ($this->player [$name]['칭호'], $prefix);
 
	}

	public function createData($player)
	{

		$name = strtolower ($player->getName());
		
		if (isset ($this->player [$name])) return true;
		
		$this->player[$name] = [];
		$this->player[$name]['닉네임'] = $player->getName();
		$this->player[$name]['메인 칭호'] = $this->db ['기본 칭호'];
		$this->player[$name]['칭호'] = [$this->db ['기본 칭호']];

	}

	public function onTouch(PlayerInteractEvent $event)
	{
		
		$player = $event->getPlayer();
		$item = $event->getItem();
		
		$prefix = $item->getNamedTagEntry('칭호');
		
		if ($event->isCancelled()) return true;
		if ($prefix !== null && $item->getId() === 421) {
			
			$prefix = $prefix->getValue();
			
			$this->addPrefix ($player, $prefix);
			$this->msg ($player, str_replace (["(칭호)"], [$prefix], $this->m ['티켓 사용 완료']));
			
			$player->getInventory()->removeItem ($item);

		}

	}

	public function onCommand(CommandSender $player, Command $command, string $label, array $args) : bool
    {
		
		$name = strtolower ($player->getName());
		
		if ($command->getName() === '칭호') {
			
			if ($player->isOp()) {
				
				if (isset ($args[0])) {
					
					if ($args[0] === '설정') {
						
						if (! isset ($args[2])) {
							
							$this->msg ($player, $this->m ['명령어 도움말']);
							return true;
							
						}
						
						$target = $this->getServer()->getOfflinePlayer ($args[1]);
						
						unset ($args[0]);
						unset ($args[1]);
						
						$prefix = implode (' ', $args);
						
						if ($this->hasPrefix ($target, $prefix)) {
							
							$this->setMainPrefix ($target, $prefix);
							$this->msg ($player, str_replace (['(플레이어)', '(칭호)'], [$target->getName(), $prefix], $this->m ['기본 칭호 변경 완료']));
							
							return true;
							
						} else {
							
							
							$this->msg ($player, str_replace (['(플레이어)'], [$target->getName()], $this->m ['칭호 미소유']));
							return true;
							
						}
						
					} else if ($args[0] === '자유칭호권') {
						
						if (! isset ($args[3])) {
							
							$this->msg ($player, $this->m ['명령어 도움말']);
							return true;
	
						}
						
						if (
							
							! is_numeric ($args[2]) ||
							! is_numeric ($args[3])
						) {
							
							$this->msg ($player, '최대 글자나 최대 색코드 수는 숫자로 입력해주세요');
							return true;
							
						}
						
						if (isset ($this->db ['자유 칭호권'][$args[1]])) {
							
							$this->msg ($player, '해당 이름의 자유칭호권은 이미 있습니다');
							return true;
							
						}
						
						$item = $player->getInventory()->getItemInHand()->jsonSerialize();
						$item ['count'] = 1;
						
						$this->db ['자유 칭호권'][$args[1]] = [
							
							'최대 글자' => $args[2],
							'최대 색코드 개수' => $args[3],
							'아이템' => $item
							
						];
						
						$this->msg ($player, '자유칭호권을 생성했습니다');
						return true;
						
					} else if ($args[0] === '추가') {
						
						if (! isset ($args[2])) {
							
							$this->msg ($player, $this->m ['명령어 도움말']);
							return true;
							
						}
						
						$target = $this->getServer()->getOfflinePlayer ($args[1]);
						
						unset ($args[0]);
						unset ($args[1]);
						
						$prefix = implode (' ', $args);
						
						if ($this->hasPrefix ($target, $prefix)) {
							
							$this->msg ($player, $target->getName() . '님은 이미 해당 칭호를 소유하고 있습니다!');
							return true;
							
						} else {
							
							$this->addPrefix ($target, $prefix);
							$this->msg ($player, '칭호 ' . $prefix . ' §r§f(을)를 추가하였습니다!');
							return true;
							
						}		
						
					} else if ($args[0] === '제거') {
						
						if (! isset ($args[2])) {
	
							$this->msg ($player, $this->m ['명령어 도움말']);
							return true;
							
						}
						
						$target = $this->getServer()->getOfflinePlayer ($args[1]);
						
						unset ($args[0]);
						unset ($args[1]);
						
						$prefix = implode (' ', $args);
						
						if ($this->hasPrefix ($target, $prefix)) {
							
							foreach ($this->player [strtolower ($target->getName())]['칭호'] as $index => $havePrefix) if ($havePrefix === $prefix) {
								
								unset ($this->player [strtolower ($target->getName())]['칭호'][$index]);
								$this->msg ($player, $target->getName() . '님에게서 칭호 ' . $prefix . ' §r§f(을)를 제거하였습니다!');
							
								return true;
								
							}
							
						} else {

							$this->msg ($player, $target->getName() . '님은 칭호 ' . $prefix . ' §r§f(을)를 소유하고 있지 않습니다!');
							return true;
							
						}		
						
						
					} else if ($args[0] === '목록') {
						
						if (! isset ($args[1])) {
							
							$this->msg ($player, $this->m ['명령어 도움말']);
							return true;
	
						}
						
						$target = $this->getServer()->getOfflinePlayer ($args[1]);
						
						$page = $args[2] ?? 1;
						if ($page < 1) $page = 1;
						
						$show = 5;
						$data = $this->getPrefixs ($target);
						$max = count ($data) / $show;
						$max = ceil ($max);
						
						if ($max < $page) $page = $max;

						$player->sendMessage ('§6§l<===== §f칭호 목록 §6§l| §r§f' . $page . ' §6§l/ §r§f' . $max . ' §6§l=====>§r');
						
						foreach ($data as $key => $value) {
							
							$key ++;
							
							if ($key >= ($page * $show - ($show - 1)) && $key <= ($page * $show)) $player->sendMessage ('§6§l[' . $key . '번] §r§f' . $value);
						}
						
						return true;
						
					} else if ($args[0] === '한닉') {
						
						if (! isset ($args[2])) {
							
							$this->msg ($player, $this->m ['명령어 도움말']);
							return true;
							
						}
						
						$target = $this->getServer()->getOfflinePlayer ($args[1]);
						
						unset ($args[0]);
						unset ($args[1]);
						
						$prefix = implode (' ', $args);
						
						$this->setNick ($target, $prefix);
						$this->msg ($player, $target->getName() . '님의 닉네임을 ' . $prefix . '§r§f (으)로 설정하였습니다');
						
						return true;
						
					} else if ($args[0] === '티켓') {
						
						if (! isset ($args[1])) {
							
							$this->msg ($player, $this->m ['명령어 도움말']);
							return true;
							
						}
						
						unset ($args[0]);
						
						$prefix = implode (' ', $args);
						
						$item = Item::get (421, 0, 1);
						
						$item->setCustomName ('§r§6§l< §f칭호 티켓 §6| §f' . $prefix . ' §r§l§6>');
						$item->setLore (['§r이 아이템을 들고 터치하면', '§r§6' . $prefix . '§r (을)를 획득합니다!']);
						$item->setNamedTagEntry (new StringTag ('칭호', $prefix));
						
						$player->getInventory()->addItem ($item);
						
						$this->msg ($player, '칭호 티켓이 지급되었습니다! 인벤토리를 확인해주세요');
						return true;
						
					} else if ($args[0] === '상점추가') {
						
						if (! isset ($args[2])) {
							
							$this->msg ($player, $this->m ['명령어 도움말']);
							return true;
							
						}
						
						$price = $args[1];
						
						unset ($args[0]);
						unset ($args[1]);
						
						$prefix = implode (' ', $args);
						
						if (isset ($this->shop [$prefix])) {
							
							$this->msg ($player, '이미 상점에 해당 칭호가 존재합니다!');
							return true;
							
						} else {
							
							$this->shop [$prefix] = $price;
							$this->msg ($player, '칭호 상점에 추가를 완료했습니다!');
							
							return true;
							
						}
						
					} else if ($args[0] === '상점제거') {
						
						if (! isset ($args[1])) {
							
							$this->msg ($player, $this->m ['명령어 도움말']);
							return true;

						}
						
						unset ($args[0]);
						
						$prefix = implode (' ', $args);
						
						if (isset ($this->shop [$prefix])) {

							unset ($this->shop [$prefix]);
							$this->msg ($player, '해당 칭호를 상점에서 제거했습니다');
							
							return true;

						}
						
						$this->msg ($player, '해당 칭호 (' . $prefix . '§r§f) 는 존재하지 않습니다');
						return true;

					} else {
						
						$this->msg ($player, $this->m ['명령어 도움말']);
						return true;
						
					}
					
				}
				
			}
			
			$this->sendUI ($player, $this->id [0], [
			
				'type' => 'form',
				'title' => $this->title,
				'content' => "\n" . '§f칭호 시스템 오류 제보 | @네오스' . "\n\n",
				
				'buttons' => [
				
					[
					
						'text' => '§l▶ 시스템 종료하기' . "\n" . '§r§8현재 열린 창을 닫습니다'
						
					],
					
					[
					
						'image' => [
							'type' => 'url',
							'data' => 'https://gamepedia.cursecdn.com/minecraft_gamepedia/b/b2/Paper.png'
						],
						
						'text' => '§l▶ 칭호 변경하기' . "\n" . '§r§8나의 기본 칭호를 변경합니다'
						
					],
					
					[
					
						'image' => [
							'type' => 'url',
							'data' => 'https://gamepedia.cursecdn.com/minecraft_gamepedia/b/b2/Paper.png'
						],
						
						'text' => '§l▶ 칭호 구매하기' . "\n" . '§r§8칭호 상점을 오픈합니다'
						
					],
					
					[
					
						'image' => [
						
							'type' => 'url',
							'data' => 'https://gamepedia.cursecdn.com/minecraft_gamepedia/b/b2/Paper.png'
							
						],
						
						'text' => '§l▶ 칭호 만들기' . "\n" . '§r§8자유칭호권이 필요합니다'
					]
				]
				
			]);
			
			return true;

		}
		
		return true;
		
	}

	public function onData(DataPacketReceiveEvent $event)
	{
		
		$player = $event->getPlayer();
		$packet = $event->getPacket();
		
		$name = strtolower ($player->getName());
		
        	if ($packet instanceof ModalFormResponsePacket) {
			
			$id = $packet->formId;
			$val = json_decode ($packet->formData, true);
			
			if ($id === $this->id [0]) {
				
				if ($val === null) return true;
				if ($val === 0) return true;
				
				if ($val === 1) {
					
					$button = [];
					$num = 0;
					
					foreach ($this->getPrefixs ($player) as $prefix) {
						
						$num ++;
						array_push ($button, ['text' => '§6§l[§r§8' . $num . '§6§l] §r§8' . $prefix]);

					}
					
					$this->sendUI ($player, $this->id [1], [
					
						'type' => 'form',
						'title' => $this->title,
						'content' => '현재 §6' . $num . ' §f개의 칭호를 소유하고 있습니다!',
						'buttons' => $button

					]);
					
				} else if ($val === 2) {
					
					$button = [];
					$num = 0;
					
					foreach ($this->shop as $prefix => $cost) {
						
						$num ++;
						array_push ($button, ['text' => '§8' . $num . '번 | §r§8' . $prefix . "\n" . '§8가격 - ' . $cost . '원']);
						
					}

					$this->sendUI ($player, $this->id [2], [
					
						'type' => 'form',
						'title' => $this->title,
						'content' => '현재 상점에는 §6' . $num . ' §f개의 칭호가 등록되어 있습니다!',
						'buttons' => $button
						
					]);
					
				} else if ($val === 3) {
					
					$buttons = [];
					$buttons [] = [
					
						'text' => '§l▶ 시스템 종료하기' . "\n" . '§r§8현재 열린 창을 닫습니다'
						
					];
					
					foreach ($this->db ['자유 칭호권'] as $key => $val) $buttons [] = [
						
						'image' => [
							
							'type' => 'url',
							'data' => 'https://gamepedia.cursecdn.com/minecraft_gamepedia/b/b2/Paper.png'
						
						],
						
						'text' => '§l▶ ' . $key . ' 자유칭호권' . "\n" . '§r§8최대 ' . $val ['최대 글자'] . '글자 / 색코드 ' . $val ['최대 색코드 개수'] . '개'
						
					];
					
					$this->sendUI ($player, $this->id [3], [
					
						'type' => 'form',
						'title' => $this->title,
						'content' => '어떤 자유칭호권을 소지하고 계신가요?',
						
						'buttons' => $buttons
						
					]);
					
				}
				
			} else if ($id === $this->id [1]) {
				
				if ($val === null) return true;
				
				$prefix = $this->getPrefixs ($player)[$val];
				
				$this->setMainPrefix ($player, $prefix);
				$this->msgUI ($player, '기본 칭호를 ' . $prefix . ' §r§f(으)로 변경하였습니다!');
				
			} else if ($id === $this->id [2]) {
				
				if ($val === null) return true;

				$prefix = array_keys ($this->shop)[$val];
				$price = $this->shop [$prefix];

				if ($this->economy->myMoney ($player) < $price) {
					
					$this->msgUI ($player, '칭호를 구매하기 위한 돈이 부족합니다! (가격 - ' . $price . ')');
					return true;
					
				}
				
				if ($this->hasPrefix ($player, $prefix)) {
						
					$this->msgUI ($player, $this->m ['이미 칭호 소유']);
					return true;
						
				}
					
				$this->economy->reduceMoney ($player, $price);
				
				$this->addPrefix ($player, $prefix);
				$this->msgUI ($player, '칭호 구매를 완료하였습니다! 칭호를 적용하려면 다시 [ /칭호 ] 를 입력하여 변경해주세요!');
				
				return true;

			} else if ($id === $this->id [3]) {
				
				if ($val === null || $val === 0) return true;
				$what = array_keys ($this->db ['자유 칭호권'])[$val - 1];
						
				$limit1 = $this->db ['자유 칭호권'][$what]['최대 글자'];
				$limit2 = $this->db ['자유 칭호권'][$what]['최대 색코드 개수'];
					
				if (
					
					! isset ($this->db ['자유 칭호권'][$what]['아이템']) ||
					! is_array ($this->db ['자유 칭호권'][$what]['아이템'])
							
				) {
							
					$item = Item::get (0);
							
				} else {
							
					$item = Item::jsonDeserialize ($this->db ['자유 칭호권'][$what]['아이템']);
							
				}
				
				$this->neos [$name] = [
					
					$limit1,
					$limit2,
					$item
					
				];

				if (! $player->getInventory()->contains ($item)) {
					
					$this->msgUI ($player, '자유칭호권을 소지하고 있지 않습니다! 인벤토리를 다시 확인해주세요');
					return true;
					
				}
				
				$this->sendUI ($player, $this->id [4], [
				
					'type' => 'custom_form',
					'title' => $this->title,
					'content' => [
					
						[
							'type' => 'label',
							'text' => '최대 글자 수는 ' . $limit1 . '글자 (색 코드 ' . $limit2 . ' 개) 입니다!'
						],
						
						[
							'type' => 'input',
							'text' => '§l▶ 칭호 입력 | 원하는 칭호를 입력해주세요',
							'placeholder' => '예) 네오스'
						]
						
					]

				]);
				
				return true;
				
			} else if ($id === $this->id [4]) {
				
				if (! isset ($this->neos [$name])) return true;
				
				if ($val[1] === null) {
					
					$this->msgUI ($player, '칭호가 입력되지 않아 작업이 취소 됬습니다!');
					return true;
					
				}
				
				$data = $this->neos [$name];
				
				$limit1 = $data [0];
				$limit2 = $data [1];
				

				if (isset (explode ('§', $val[1])[$limit2 + 1])) {
					
					$this->msgUI ($player, '색 코드는 최대 ' . $limit2 . '개를 사용할 수 있습니다!');
					return true;

				}
				
				$clean = TextFormat::clean ($val[1]);
				$count = mb_strlen ($clean, 'utf-8');
				
				if ($count > $limit1) {
					
					$this->msgUI ($player, '자유칭호는 최대 ' . $limit1 . '글자를 입력할 수 있습니다 (' . $count . '글자 입력함)');
					return true;
					
				}
				
				if (!$player->getInventory()->contains ($data [2])) {
					
					$this->msgUI ($player, '자유칭호권을 소지하고 있지 않습니다!');
					return true;
					
				}
				
				if ($this->hasPrefix ($player, $val[1])) {
					
					$this->msgUI ($player, '해당 칭호를 이미 소유하고 있습니다!');
					return true;

				}
				
				$player->getInventory()->removeItem ($data [2]);

				$this->addPrefix ($player, $val[1]);
				$this->msgUI ($player, '칭호 ' . $val[1] . '§r§f을 교환하였습니다!');
				
				unset ($this->neos [$name]);

			}
			
		}
		
	}
}
