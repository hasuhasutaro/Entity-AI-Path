<?php
namespace noahasu\entityAI;

use noahasu\entityAI\AStar;
use pocketmine\entity\EntityFactory;
use pocketmine\event\Listener;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\plugin\PluginBase;
use pocketmine\world\World;

use noahasu\entityAI\entity\FollowPathEntity;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\entity\EntityDataHelper;
use pocketmine\player\Player;
use pocketmine\world\Position;

class Main extends PluginBase implements Listener {
    private ?Position $pPos1 = null;
    private ?Position $pPos2 = null;
    public function onEnable() : void {
        $this -> getServer() -> getPluginManager() -> registerEvents($this, $this);
        $this -> registerEntity();
    }

    public function registerEntity() {
        EntityFactory::getInstance() -> register(FollowPathEntity::class, function(World $world, CompoundTag $nbt) : FollowPathEntity{
			return new FollowPathEntity(EntityDataHelper::parseLocation($nbt, $world), $nbt);
		}, ['FollowPathEntity', FollowPathEntity::getNetworkTypeId()]);
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
    {
        if(!$sender instanceof Player) return false;

        switch($label) {
            case 'pos':
                $pPos = $sender -> getLocation();
                if($this -> pPos1 === null) {
                    $this -> pPos1 = $pPos;
                    $sender -> sendMessage('pos1をセットしました。');
                    return true;
                }
                if($this -> pPos2 === null) {
                    $this -> pPos2 = $pPos;
                    $sender -> sendMessage('pos2をセットしました。');
                    return true;
                }
                
                $world = $pPos -> getWorld();

                $entity = new FollowPathEntity($this -> pPos2);
                $entity -> setMovePosition($this -> pPos1);
                
                if(count($args) === 1) {
                    $entity -> setMovementSpeed((int)$args[0]);
                }

                $entity -> spawnToAll();
                $this -> pPos1 = null;
                $this -> pPos2 = null;
            break;
        }

        return true;
    }
}